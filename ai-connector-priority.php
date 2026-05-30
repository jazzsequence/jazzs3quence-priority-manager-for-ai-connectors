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
 * Returns active providers that support a given task, as ID => label pairs.
 *
 * Derives which providers support the task from the AI plugin's default model
 * list, then appends any active connectors not yet present in that list.
 *
 * @param string                                        $task   Task type: 'text', 'image', or 'vision'.
 * @param array<int, array{0: string, 1: string}>|null $models Pre-fetched model list, or null to apply the filter.
 * @return array<string, string> Provider ID => display label.
 */
function get_providers_for_task( string $task, ?array $models = null ): array {
	if ( null === $models ) {
		$models = get_default_models_for_task( $task );
	}

	$active = get_active_connectors();
	$labels = [];

	foreach ( $models as [ $provider ] ) {
		if ( isset( $active[ $provider ] ) && ! isset( $labels[ $provider ] ) ) {
			$labels[ $provider ] = $active[ $provider ]['name'];
		}
	}

	return $labels;
}

/**
 * Returns the AI plugin's default model list for a task by applying its filter
 * without our own hook attached, so we see the baseline ordering.
 *
 * @param string $task Task type: 'text', 'image', or 'vision'.
 * @return array<int, array{0: string, 1: string}>
 */
function get_default_models_for_task( string $task ): array {
	$map = [
		'text'   => [ 'wpai_preferred_text_models', __NAMESPACE__ . '\reorder_models_for_text' ],
		'image'  => [ 'wpai_preferred_image_models', __NAMESPACE__ . '\reorder_models_for_image' ],
		'vision' => [ 'wpai_preferred_vision_models', __NAMESPACE__ . '\reorder_models_for_vision' ],
	];

	if ( ! isset( $map[ $task ] ) ) {
		return [];
	}

	[ $filter, $callback ] = $map[ $task ];
	$priority              = has_filter( $filter, $callback );

	if ( false !== $priority ) {
		remove_filter( $filter, $callback, $priority );
	}

	/*
	 * Call the AI plugin's helpers, not apply_filters() with [].
	 * Defaults are passed as the second arg to apply_filters() inside those
	 * functions; calling the filter directly with [] would bypass them.
	 */
	$models = match ( $task ) {
		'text'   => function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' )
						? (array) \WordPress\AI\get_preferred_models_for_text_generation()
						: [],
		'image'  => function_exists( 'WordPress\AI\get_preferred_image_models' )
						? (array) \WordPress\AI\get_preferred_image_models()
						: [],
		'vision' => function_exists( 'WordPress\AI\get_preferred_vision_models' )
						? (array) \WordPress\AI\get_preferred_vision_models()
						: [],
	};

	if ( false !== $priority ) {
		add_filter( $filter, $callback );
	}

	return $models;
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
 * Renders the AI Priority settings page.
 *
 * @return void
 */
function render_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$saved = false;

	if (
		isset( $_POST['_cc_ai_priority_nonce'] ) &&
		wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_cc_ai_priority_nonce'] ) ), 'cc_ai_priority_save' )
	) {
		$saved = save_priorities();
	}

	$active     = get_active_connectors();
	$priorities = get_priorities();
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

				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php esc_html_e( 'Provider', 'ai-connector-priority' ); ?></label>
							</th>
							<td>
								<select name="cc_ai_priority[<?php echo esc_attr( $task ); ?>]" id="<?php echo esc_attr( $field_id ); ?>">
									<?php foreach ( $providers as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $priorities[ $task ], $value ); ?>>
											<?php echo esc_html( $label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
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
