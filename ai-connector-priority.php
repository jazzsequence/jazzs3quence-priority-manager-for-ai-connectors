<?php
/**
 * Plugin Name:       AI Connector Priority
 * Plugin URI:        https://github.com/jazzsequence/ai-connector-priority
 * Description:       Admin settings page to configure which AI provider is tried first for each task type (text, image, vision). Requires the WordPress AI plugin (wordpress.org/plugins/ai).
 * Version:           1.0.0
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
 * the provider plugin file; for custom providers (e.g. Vertex) that have no plugin file
 * key, it returns true unconditionally — so any registered provider plugin is included.
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
 * Returns active providers that appear in a task's model list, as ID => label pairs.
 *
 * Derives which providers support the task by looking at which provider IDs appear
 * in the incoming filter value for that task, then intersects with the active connectors.
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

	// Providers that appear in the model list, in model-list order.
	foreach ( $models as [ $provider ] ) {
		if ( isset( $active[ $provider ] ) && ! isset( $labels[ $provider ] ) ) {
			$labels[ $provider ] = $active[ $provider ]['name'];
		}
	}

	// Active connectors not yet in the default model list get appended.
	foreach ( $active as $id => $connector ) {
		if ( ! isset( $labels[ $id ] ) ) {
			$labels[ $id ] = $connector['name'];
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
 * Returns the saved provider priority order for all task types, merged with defaults.
 *
 * Defaults are derived from the active connectors visible in each task's model list,
 * preserving the order the AI plugin provides them.
 *
 * @return array{text: string[], image: string[], vision: string[]} Task type => ordered provider IDs.
 */
function get_priorities(): array {
	$defaults = [];

	foreach ( [ 'text', 'image', 'vision' ] as $task ) {
		$defaults[ $task ] = array_keys( get_providers_for_task( $task ) );
	}

	return wp_parse_args( get_option( OPTION_KEY, [] ), $defaults );
}

/**
 * Reorders an incoming model list according to the saved provider priorities.
 *
 * Models for inactive providers are dropped. Models for active providers not
 * in the saved priorities are appended at the end in their original order.
 *
 * @param array<int, array{0: string, 1: string}> $models Incoming [provider, model] pairs.
 * @param string                                   $task   Task type: 'text', 'image', or 'vision'.
 * @return array<int, array{0: string, 1: string}> Reordered list.
 */
function reorder_model_list( array $models, string $task ): array {
	$active     = array_keys( get_active_connectors() );
	$priorities = get_priorities()[ $task ];

	// Group active models by provider, preserving per-provider order.
	$by_provider = [];
	foreach ( $models as $pair ) {
		[ $provider ] = $pair;
		if ( in_array( $provider, $active, true ) ) {
			$by_provider[ $provider ][] = $pair;
		}
	}

	// Assemble in priority order, then append any providers not yet prioritised.
	$result = [];
	foreach ( $priorities as $provider ) {
		if ( isset( $by_provider[ $provider ] ) ) {
			array_push( $result, ...$by_provider[ $provider ] );
			unset( $by_provider[ $provider ] );
		}
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

/**
 * Renders the AI Priority settings page.
 *
 * Handles form submission (verified via nonce) before rendering so the page
 * reflects saved values immediately after saving.
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
			'description' => __( 'Used for: title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions.', 'ai-connector-priority' ),
		],
		'image'  => [
			'label'       => __( 'Image Generation', 'ai-connector-priority' ),
			'description' => __( 'Used for: featured image generation, inline image generation, image editing.', 'ai-connector-priority' ),
		],
		'vision' => [
			'label'       => __( 'Vision', 'ai-connector-priority' ),
			'description' => __( 'Used for: alt text generation, image analysis.', 'ai-connector-priority' ),
		],
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Connector Priority', 'ai-connector-priority' ); ?></h1>
		<p><?php esc_html_e( 'Set which provider to try first for each AI task type. Only active provider plugins are shown. If the first-choice provider fails, the next one is tried automatically.', 'ai-connector-priority' ); ?></p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Priority settings saved.', 'ai-connector-priority' ); ?></p></div>
		<?php endif; ?>

		<?php if ( empty( $active ) ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'No AI provider plugins are active. Install and activate at least one provider plugin to configure priorities.', 'ai-connector-priority' ); ?>
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
				?>
				<h2><?php echo esc_html( $info['label'] ); ?></h2>
				<p class="description"><?php echo esc_html( $info['description'] ); ?></p>

				<table class="form-table" role="presentation">
					<tbody>
					<?php
					$provider_keys = array_keys( $providers );
					$current_order = $priorities[ $task ];
					$ordinals      = [
						__( '1st choice', 'ai-connector-priority' ),
						__( '2nd choice', 'ai-connector-priority' ),
						__( '3rd choice', 'ai-connector-priority' ),
					];

					foreach ( $provider_keys as $position => $default_provider ) :
						$label    = $ordinals[ $position ] ?? ( ( $position + 1 ) . 'th choice' );
						$selected = $current_order[ $position ] ?? $default_provider;
						$field_id = "cc_ai_{$task}_{$position}";
						?>
						<tr>
							<th scope="row">
								<label for="<?php echo esc_attr( $field_id ); ?>"><?php echo esc_html( $label ); ?></label>
							</th>
							<td>
								<select name="cc_ai_priority[<?php echo esc_attr( $task ); ?>][]" id="<?php echo esc_attr( $field_id ); ?>">
									<?php foreach ( $providers as $value => $provider_label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $selected, $value ); ?>>
											<?php echo esc_html( $provider_label ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save Priority Settings', 'ai-connector-priority' ) ); ?>
		</form>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Sanitizes and persists the submitted provider priority order to wp_options.
 *
 * Called only after nonce verification in render_page().
 *
 * @return bool True if the option was updated, false if unchanged or on failure.
 */
function save_priorities(): bool {
	// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified by caller; values sanitized in sanitize_provider_order().
	$raw = isset( $_POST['cc_ai_priority'] ) && is_array( $_POST['cc_ai_priority'] ) ? wp_unslash( $_POST['cc_ai_priority'] ) : [];

	$clean = [];

	foreach ( array_keys( get_priorities() ) as $task ) {
		if ( isset( $raw[ $task ] ) && is_array( $raw[ $task ] ) ) {
			$valid          = array_keys( get_providers_for_task( $task ) );
			$clean[ $task ] = sanitize_provider_order( array_values( $raw[ $task ] ), $valid );
		}
	}

	return (bool) update_option( OPTION_KEY, $clean );
}

/**
 * Deduplicates and validates a submitted provider order array.
 *
 * First occurrence of each valid provider ID wins. Any valid provider absent from
 * the submitted list is appended in its default position so the returned array
 * always contains every valid provider exactly once.
 *
 * @param string[] $submitted  Raw submitted provider IDs (unsanitized).
 * @param string[] $valid      Allowed provider IDs for this task type.
 * @return string[]            Sanitized, deduplicated, complete provider order.
 */
function sanitize_provider_order( array $submitted, array $valid ): array {
	$seen   = [];
	$result = [];

	foreach ( $submitted as $provider ) {
		$provider = sanitize_key( $provider );
		if ( in_array( $provider, $valid, true ) && ! in_array( $provider, $seen, true ) ) {
			$result[] = $provider;
			$seen[]   = $provider;
		}
	}

	foreach ( $valid as $provider ) {
		if ( ! in_array( $provider, $seen, true ) ) {
			$result[] = $provider;
		}
	}

	return $result;
}
