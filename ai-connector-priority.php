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
 * Author URI:        https://github.com/jazzsequence
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       ai-connector-priority
 */

namespace AiConnectorPriority;

const OPTION_KEY = 'ai_connector_priority';
const PAGE_SLUG  = 'ai-connector-priority';

/**
 * Returns the available AI providers for a given task type.
 *
 * Anthropic is excluded from image generation as it does not support that capability.
 *
 * @param string $task Task type: 'text', 'image', or 'vision'.
 * @return array<string, string> Provider ID => display label.
 */
function get_providers_for_task( string $task ): array {
	$all = [
		'anthropic' => 'Anthropic (Claude)',
		'google'    => 'Google (Gemini)',
		'openai'    => 'OpenAI',
	];

	if ( 'image' === $task ) {
		unset( $all['anthropic'] );
	}

	return $all;
}

/**
 * Returns the ordered list of provider/model pairs for a given provider and task type.
 *
 * Each entry is a two-element array of [provider_id, model_id] as expected by the
 * WordPress AI plugin's wpai_preferred_*_models filters.
 *
 * @param string $provider Provider ID: 'anthropic', 'google', or 'openai'.
 * @param string $task     Task type: 'text', 'image', or 'vision'.
 * @return array<int, array{0: string, 1: string}> Ordered list of [provider, model] pairs.
 */
function get_models_for_provider( string $provider, string $task ): array {
	$map = [
		'text' => [
			'anthropic' => [ [ 'anthropic', 'claude-sonnet-4-6' ] ],
			'google'    => [ [ 'google', 'gemini-3-flash-preview' ], [ 'google', 'gemini-2.5-flash' ] ],
			'openai'    => [ [ 'openai', 'gpt-5.4-mini' ], [ 'openai', 'gpt-4.1-mini' ] ],
		],
		'image' => [
			'google' => [
				[ 'google', 'gemini-3.1-flash-image-preview' ],
				[ 'google', 'gemini-3-pro-image-preview' ],
				[ 'google', 'gemini-2.5-flash-image' ],
			],
			'openai' => [ [ 'openai', 'gpt-image-2' ], [ 'openai', 'gpt-image-1.5' ] ],
		],
		'vision' => [
			'anthropic' => [ [ 'anthropic', 'claude-sonnet-4-6' ] ],
			'google'    => [ [ 'google', 'gemini-3-flash-preview' ], [ 'google', 'gemini-2.5-flash' ] ],
			'openai'    => [ [ 'openai', 'gpt-5.4-mini' ], [ 'openai', 'gpt-4.1-mini' ] ],
		],
	];

	return $map[ $task ][ $provider ] ?? [];
}

/**
 * Returns the saved provider priority order for all task types, merged with defaults.
 *
 * @return array{text: string[], image: string[], vision: string[]} Task type => ordered provider IDs.
 */
function get_priorities(): array {
	$defaults = [
		'text'   => [ 'anthropic', 'google', 'openai' ],
		'image'  => [ 'openai', 'google' ],
		'vision' => [ 'anthropic', 'google', 'openai' ],
	];

	return wp_parse_args( get_option( OPTION_KEY, [] ), $defaults );
}

/**
 * Builds the ordered [provider, model] list for a task type based on saved priorities.
 *
 * Iterates the saved provider order and appends each provider's model list in sequence,
 * producing the array expected by wpai_preferred_*_models filters.
 *
 * @param string $task Task type: 'text', 'image', or 'vision'.
 * @return array<int, array{0: string, 1: string}> Ordered list of [provider, model] pairs.
 */
function build_model_list( string $task ): array {
	$priorities = get_priorities();
	$models     = [];

	foreach ( $priorities[ $task ] as $provider ) {
		$models = array_merge( $models, get_models_for_provider( $provider, $task ) );
	}

	return $models;
}

add_filter( 'wpai_preferred_text_models', static fn() => build_model_list( 'text' ) );
add_filter( 'wpai_preferred_image_models', static fn() => build_model_list( 'image' ) );
add_filter( 'wpai_preferred_vision_models', static fn() => build_model_list( 'vision' ) );
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

	$priorities = get_priorities();
	$tasks      = [
		'text'   => [
			'label'       => __( 'Text Generation', 'ai-connector-priority' ),
			'description' => __( 'Used for: title generation, excerpt, summarization, content resizing, editorial notes, meta descriptions.', 'ai-connector-priority' ),
		],
		'image'  => [
			'label'       => __( 'Image Generation', 'ai-connector-priority' ),
			'description' => __( 'Used for: featured image generation, inline image generation, image editing. Anthropic does not support image generation.', 'ai-connector-priority' ),
		],
		'vision' => [
			'label'       => __( 'Vision', 'ai-connector-priority' ),
			'description' => __( 'Used for: alt text generation, image analysis.', 'ai-connector-priority' ),
		],
	];
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'AI Connector Priority', 'ai-connector-priority' ); ?></h1>
		<p><?php esc_html_e( 'Set which provider to try first for each AI task type. Only providers with a configured API key in Settings → Connectors are used. If the first-choice provider is not registered, the next one is tried automatically.', 'ai-connector-priority' ); ?></p>

		<?php if ( $saved ) : ?>
			<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Priority settings saved.', 'ai-connector-priority' ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'cc_ai_priority_save', '_cc_ai_priority_nonce' ); ?>

			<?php foreach ( $tasks as $task => $info ) : ?>
				<h2><?php echo esc_html( $info['label'] ); ?></h2>
				<p class="description"><?php echo esc_html( $info['description'] ); ?></p>

				<table class="form-table" role="presentation">
					<tbody>
					<?php
					$providers     = get_providers_for_task( $task );
					$provider_keys = array_keys( $providers );
					$current_order = $priorities[ $task ];
					$labels        = [
						__( '1st choice', 'ai-connector-priority' ),
						__( '2nd choice', 'ai-connector-priority' ),
						__( '3rd choice', 'ai-connector-priority' ),
					];

					foreach ( $provider_keys as $position => $default_provider ) :
						$label    = $labels[ $position ] ?? ( ( $position + 1 ) . 'th choice' );
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

	foreach ( [ 'text', 'image', 'vision' ] as $task ) {
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
