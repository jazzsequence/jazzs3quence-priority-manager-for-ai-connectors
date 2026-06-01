<?php
/**
 * Plugin Name:       AI Connector Priority
 * Plugin URI:        https://github.com/jazzsequence/ai-connector-priority
 * Description:       Choose which AI provider to use for each task type (text, image, vision). Requires the WordPress AI plugin (wordpress.org/plugins/ai) and at least one active provider plugin.
 * Version:           1.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.2
 * Requires Plugins:  ai
 * Author:            Chris Reynolds
 * Author URI:        https://next.jazzsequence.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       ai-connector-priority
 */

namespace AiConnectorPriority;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const OPTION_KEY = 'ai_connector_priority';
const PAGE_SLUG  = 'ai-connector-priority';

/**
 * Returns registered AI provider connectors from the WordPress AI plugin.
 *
 * Uses WordPress\AI\get_ai_connectors(true) which filters via is_connector_plugin_active():
 * for built-in providers (anthropic, google, openai) that checks is_plugin_active() on
 * the provider plugin file; for custom providers that have no plugin file key it returns
 * true unconditionally — so any registered provider plugin is included.
 *
 * Returns an empty array when the AI plugin is not loaded.
 *
 * @return array<string, array{name: string}> Provider ID => connector data.
 */
function get_active_connectors(): array {
	if ( ! function_exists( 'WordPress\AI\get_ai_connectors' ) ) {
		return [];
	}

	return \WordPress\AI\get_ai_connectors( true );
}

/**
 * Returns the task types a given provider supports, using a WordPress transient
 * as a 24-hour cache so the AiClient capability check only fires once per day.
 *
 * When wp_ai_client_prompt() is unavailable (AI plugin not loaded) or the
 * provider is not configured (no credentials), all tasks are returned so the
 * provider is visible in the UI rather than silently hidden.
 *
 * @param string $provider_id Provider ID as registered with the AI plugin.
 * @return string[] Supported task types: any combination of 'text', 'image', 'vision'.
 */
function get_provider_supported_tasks( string $provider_id ): array {
	$transient_key = 'aicp_tasks_' . sanitize_key( $provider_id );
	$cached        = get_transient( $transient_key );

	if ( false !== $cached ) {
		return (array) $cached;
	}

	/*
	 * Image generation is specialized — most providers don't support it.
	 * Default to text + vision so unconfigured providers are never shown
	 * for image tasks they cannot handle.
	 */
	$default_tasks = [ 'text', 'vision' ];

	if ( ! class_exists( 'WordPress\AiClient\AiClient' ) ) {
		set_transient( $transient_key, $default_tasks, DAY_IN_SECONDS );
		return $default_tasks;
	}

	$tasks = [];

	try {
		$registry = \WordPress\AiClient\AiClient::defaultRegistry();

		if ( ! $registry->isProviderConfigured( $provider_id ) ) {
			set_transient( $transient_key, $default_tasks, DAY_IN_SECONDS );
			return $default_tasks;
		}

		$text_reqs = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
			[ \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration() ],
			[]
		);

		if ( ! empty( $registry->findProviderModelsMetadataForSupport( $provider_id, $text_reqs ) ) ) {
			$tasks[] = 'text';
			// Vision = text generation with image input; if text gen works, vision works.
			$tasks[] = 'vision';
		}

		$image_reqs = new \WordPress\AiClient\Providers\Models\DTO\ModelRequirements(
			[ \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration() ],
			[]
		);

		if ( ! empty( $registry->findProviderModelsMetadataForSupport( $provider_id, $image_reqs ) ) ) {
			$tasks[] = 'image';
		}
	} catch ( \Throwable $e ) {
		set_transient( $transient_key, $default_tasks, DAY_IN_SECONDS );
		return $default_tasks;
	}

	if ( empty( $tasks ) ) {
		set_transient( $transient_key, $default_tasks, DAY_IN_SECONDS );
		return $default_tasks;
	}

	set_transient( $transient_key, $tasks, DAY_IN_SECONDS );
	return $tasks;
}

/**
 * Returns active providers that support a given task, as ID => label pairs.
 *
 * Capability information comes from the AiClient registry, cached in WordPress
 * transients. Providers without cached capabilities (not yet configured) are
 * shown for all tasks so they remain visible in the UI.
 *
 * @param string $task Task type: 'text', 'image', or 'vision'.
 * @return array<string, string> Provider ID => display label.
 */
function get_providers_for_task( string $task ): array {
	$active = get_active_connectors();
	$labels = [];

	foreach ( $active as $id => $connector ) {
		if ( in_array( $task, get_provider_supported_tasks( $id ), true ) ) {
			$labels[ $id ] = $connector['name'];
		}
	}

	return $labels;
}


/**
 * Returns the saved preferred provider per task type, merged with defaults.
 *
 * Stores a single provider ID per task. The selected provider's models are
 * moved to the front of the AI plugin's model list; all others remain behind
 * it in their default order.
 *
 * Migrates from the 1.0.x format (ordered array per task) by taking the
 * first element of any saved array value.
 *
 * @return array{text: string, image: string, vision: string} Task => provider ID.
 */
function get_priorities(): array {
	$saved  = (array) get_option( OPTION_KEY, [] );
	$result = [];

	foreach ( [ 'text', 'image', 'vision' ] as $task ) {
		$raw = $saved[ $task ] ?? null;

		// Migrate from old ordered-array format.
		if ( is_array( $raw ) ) {
			$raw = $raw[0] ?? '';
		}

		if ( is_string( $raw ) && '' !== $raw ) {
			$result[ $task ] = $raw;
		} else {
			$providers        = get_providers_for_task( $task );
			$result[ $task ]  = array_key_first( $providers ) ?? '';
		}
	}

	return $result;
}

/**
 * Reorders an incoming model list so the saved preferred provider's models come first.
 *
 * Models for inactive providers are dropped. All other active providers follow
 * in their original order.
 *
 * @param array<int, array{0: string, 1: string}> $models Incoming [provider, model] pairs.
 * @param string                                   $task   Task type: 'text', 'image', or 'vision'.
 * @return array<int, array{0: string, 1: string}> Reordered list.
 */
function reorder_model_list( array $models, string $task ): array {
	$preferred   = get_priorities()[ $task ] ?? '';
	$active      = array_keys( get_active_connectors() );
	$by_provider = [];

	foreach ( $models as $pair ) {
		[ $provider ] = $pair;
		if ( in_array( $provider, $active, true ) ) {
			$by_provider[ $provider ][] = $pair;
		}
	}

	$result = [];

	if ( $preferred && isset( $by_provider[ $preferred ] ) ) {
		array_push( $result, ...$by_provider[ $preferred ] );
		unset( $by_provider[ $preferred ] );
	}

	foreach ( $by_provider as $pairs ) {
		array_push( $result, ...$pairs );
	}

	return $result;
}

// Named callbacks so get_default_models_for_task() can temporarily remove them.

/**
 * Filter callback for wpai_preferred_text_models.
 *
 * @param array<int, array{0: string, 1: string}> $models Incoming model list.
 * @return array<int, array{0: string, 1: string}>
 */
function reorder_models_for_text( array $models ): array {
	return reorder_model_list( $models, 'text' );
}

/**
 * Filter callback for wpai_preferred_image_models.
 *
 * @param array<int, array{0: string, 1: string}> $models Incoming model list.
 * @return array<int, array{0: string, 1: string}>
 */
function reorder_models_for_image( array $models ): array {
	return reorder_model_list( $models, 'image' );
}

/**
 * Filter callback for wpai_preferred_vision_models.
 *
 * @param array<int, array{0: string, 1: string}> $models Incoming model list.
 * @return array<int, array{0: string, 1: string}>
 */
function reorder_models_for_vision( array $models ): array {
	return reorder_model_list( $models, 'vision' );
}

add_filter( 'wpai_preferred_text_models', __NAMESPACE__ . '\reorder_models_for_text' );
add_filter( 'wpai_preferred_image_models', __NAMESPACE__ . '\reorder_models_for_image' );
add_filter( 'wpai_preferred_vision_models', __NAMESPACE__ . '\reorder_models_for_vision' );

/**
 * Returns the map of task types to the AI plugin feature IDs that use them.
 *
 * Feature IDs are taken from the AI plugin's experiment/feature classes (those
 * that call set_provider_model_preference(), which is the only path through which
 * Developer Mode overrides can apply). This list must be kept in sync with the
 * AI plugin — add new entries here when the AI plugin introduces new features.
 *
 * Features that call using_model_preference() directly (e.g. comment-moderation)
 * are NOT included because they cannot have per-feature Developer Mode overrides.
 *
 * @return array<string, string[]> Task type => list of feature IDs.
 */
function get_task_feature_map(): array {
	return [
		'text'   => [
			'title-generation',
			'excerpt-generation',
			'summarization',
			'editorial-notes',
			'editorial-updates',
			'content-resizing',
			'meta-description',
			'content-classification',
		],
		'image'  => [ 'image-generation' ],
		'vision' => [ 'alt-text-generation' ],
	];
}

/**
 * Returns true when every feature in a task type has a Developer Mode override,
 * meaning the provider selector for that task has no effect at all.
 *
 * @param string $task Task type: 'text', 'image', or 'vision'.
 * @return bool
 */
function is_task_fully_overridden( string $task ): bool {
	$feature_ids = get_task_feature_map()[ $task ] ?? [];

	if ( empty( $feature_ids ) ) {
		return false;
	}

	foreach ( $feature_ids as $feature_id ) {
		if ( empty( get_option( "wpai_feature_{$feature_id}_field_developer", [] ) ) ) {
			return false;
		}
	}

	return true;
}

/**
 * Returns all active Developer Mode overrides grouped by task type.
 *
 * @return array<string, string[]> Task type => list of overridden feature IDs.
 */
function get_developer_mode_overrides_by_task(): array {
	$result = [];

	foreach ( get_task_feature_map() as $task => $feature_ids ) {
		foreach ( $feature_ids as $feature_id ) {
			if ( ! empty( get_option( "wpai_feature_{$feature_id}_field_developer", [] ) ) ) {
				$result[ $task ][] = $feature_id;
			}
		}
	}

	return $result;
}

/**
 * Returns the task types for which at least one feature has a Developer Mode
 * override configured in the AI plugin.
 *
 * The AI plugin stores Developer Mode config in wp_options as
 * `wpai_feature_{feature_id}_field_developer`. A non-empty value means an
 * admin has explicitly chosen a provider and model for that feature,
 * which takes precedence over this plugin's task-level selection.
 *
 * @return string[] Task type IDs that have at least one active override.
 */
function get_developer_mode_overridden_tasks(): array {
	$overridden = [];

	foreach ( get_task_feature_map() as $task => $feature_ids ) {
		foreach ( $feature_ids as $feature_id ) {
			$config = get_option( "wpai_feature_{$feature_id}_field_developer", [] );
			if ( ! empty( $config ) ) {
				$overridden[] = $task;
				break;
			}
		}
	}

	return $overridden;
}

add_action(
	'admin_menu',
	static function (): void {
		add_options_page(
			__( 'AI Connector Priority', 'ai-connector-priority' ),
			__( 'AI Priority', 'ai-connector-priority' ),
			'manage_options',
			PAGE_SLUG,
			__NAMESPACE__ . '\render_page'
		);
	}
);

add_action(
	'admin_head',
	static function (): void {
		$screen = get_current_screen();
		if ( ! $screen || 'settings_page_' . PAGE_SLUG !== $screen->id ) {
			return;
		}
		?>
		<style>
			.aicp-developer-mode-notice {
				font-style: italic;
				color: #cc0000;
				margin: 0 1em;
			}
		</style>
		<?php
	}
);

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	static function ( array $links ): array {
		$links['configure'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'options-general.php?page=' . PAGE_SLUG ) ),
			esc_html__( 'Configure', 'ai-connector-priority' )
		);
		return $links;
	}
);

/**
 * Clears cached provider capability transients so the next settings page load
 * re-detects them. Called when a plugin is activated or deactivated.
 *
 * @return void
 */
function clear_capability_cache(): void {
	$connectors = get_active_connectors();
	foreach ( array_keys( $connectors ) as $id ) {
		delete_transient( 'aicp_tasks_' . sanitize_key( $id ) );
	}
}

add_action( 'activated_plugin', __NAMESPACE__ . '\clear_capability_cache' );
add_action( 'deactivated_plugin', __NAMESPACE__ . '\clear_capability_cache' );

/**
 * Renders the AI Priority settings page.
 *
 * @return void
 */
function render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	/*
	 * Re-detect provider capabilities on every settings page load.
	 * Transients serve the filter hooks on all other page loads;
	 * the settings page makes a metadata request to each provider API
	 * and writes fresh transients for the rest of the site.
	 */
	clear_capability_cache();

	$saved = false;

	if (
		isset( $_POST['_cc_ai_priority_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cc_ai_priority_nonce'] ) ), 'cc_ai_priority_save' )
	) {
		$saved = save_priorities();
	}

	$active           = get_active_connectors();
	$priorities       = get_priorities();
	$overrides_by_task = get_developer_mode_overrides_by_task();
	$tasks      = [
		'text'   => [
			'label'       => __( 'Text Generation', 'ai-connector-priority' ),
			'description' => __( 'Used for: title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions, comment moderation.', 'ai-connector-priority' ),
		],
		'image'  => [
			'label'       => __( 'Image Generation', 'ai-connector-priority' ),
			'description' => __( 'Used for: featured image generation, inline image generation.', 'ai-connector-priority' ),
		],
		'vision' => [
			'label'       => __( 'Vision', 'ai-connector-priority' ),
			'description' => __( 'Used for: alt text generation, image analysis.', 'ai-connector-priority' ),
		],
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Connector Priority', 'ai-connector-priority' ); ?></h1>
		<p><?php esc_html_e( 'Choose which AI provider to use for each task type. Only active provider plugins are shown.', 'ai-connector-priority' ); ?></p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'ai-connector-priority' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $active ) ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'No AI provider plugins are active. Install and activate at least one provider plugin to configure your preferred provider.', 'ai-connector-priority' ); ?>
				</p>
			</div>
		<?php else : ?>
		<form method="post">
			<?php wp_nonce_field( 'cc_ai_priority_save', '_cc_ai_priority_nonce' ); ?>

			<?php foreach ( $tasks as $task => $info ) : ?>
				<?php
				$providers = get_providers_for_task( $task );
				if ( empty( $providers ) ) {
					continue;
				}
				$field_id = 'cc_ai_' . $task;
				?>
				<h2><?php echo esc_html( $info['label'] ); ?></h2>
				<p class="description"><?php echo esc_html( $info['description'] ); ?></p>

				<?php
				$overridden_features = $overrides_by_task[ $task ] ?? [];
				$task_overridden     = ! empty( $overridden_features );
				$task_disabled       = $task_overridden && is_task_fully_overridden( $task );
				?>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Provider', 'ai-connector-priority' ); ?></label>
							</th>
							<td>
								<select name="cc_ai_priority[<?php echo esc_attr( $task ); ?>]" id="<?php echo esc_attr( $field_id ); ?>" <?php disabled( $task_disabled ); ?>>
									<?php foreach ( $providers as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $priorities[ $task ], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
								<?php if ( $task_overridden ) : ?>
									<span class="description aicp-developer-mode-notice">
										<?php if ( $task_disabled ) : ?>
											<?php esc_html_e( 'Overridden by AI plugin — this selection has no effect.', 'ai-connector-priority' ); ?>
										<?php else : ?>
											<?php
											echo esc_html__( 'Overridden by AI plugin for:', 'ai-connector-priority' ) . ' ';
											echo esc_html( implode( ', ', $overridden_features ) ) . '.';
											?>
										<?php endif; ?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save Settings', 'ai-connector-priority' ) ); ?>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Sanitizes and persists the submitted provider preference to wp_options.
 *
 * @return bool True if the option was updated, false if unchanged or on failure.
 */
function save_priorities(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by caller.
	$raw   = isset( $_POST['cc_ai_priority'] ) && is_array( $_POST['cc_ai_priority'] ) ? wp_unslash( $_POST['cc_ai_priority'] ) : [];
	$clean = [];

	foreach ( [ 'text', 'image', 'vision' ] as $task ) {
		if ( isset( $raw[ $task ] ) && is_string( $raw[ $task ] ) ) {
			$provider = sanitize_key( $raw[ $task ] );
			$valid    = array_keys( get_providers_for_task( $task ) );
			if ( in_array( $provider, $valid, true ) ) {
				$clean[ $task ] = $provider;
			}
		}
	}

	return (bool) update_option( OPTION_KEY, $clean );
}
