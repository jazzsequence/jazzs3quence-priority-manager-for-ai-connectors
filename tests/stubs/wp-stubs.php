<?php
/**
 * WordPress function stubs for unit tests. No WordPress loaded.
 *
 * Only stubs functions actually called when ai-connector-priority.php is loaded
 * or when the pure business-logic functions under test are invoked.
 */

namespace WordPress\AI {
	if ( ! function_exists( 'WordPress\AI\get_ai_connectors' ) ) {
		/**
		 * Stub for WordPress\AI\get_ai_connectors().
		 *
		 * _test_ai_connectors     — installed connectors (plugin active, credentials may be absent)
		 * _test_active_connectors — subset with credentials configured ($active_only = true)
		 *
		 * If _test_active_connectors is not set, $active_only falls back to the full list,
		 * matching production behaviour where the distinction doesn't matter.
		 */
		function get_ai_connectors( bool $active_only = true ): array {
			if ( $active_only && isset( $GLOBALS['_test_active_connectors'] ) ) {
				return $GLOBALS['_test_active_connectors'];
			}
			return $GLOBALS['_test_ai_connectors'] ?? [];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' ) ) {
		/** Stub: returns a fixed baseline text model list for unit tests. */
		function get_preferred_models_for_text_generation(): array {
			return [
				[ 'anthropic', 'claude-test' ],
				[ 'google', 'gemini-test' ],
				[ 'openai', 'gpt-test' ],
			];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_image_models' ) ) {
		/** Stub: returns a fixed baseline image model list for unit tests. */
		function get_preferred_image_models(): array {
			return [
				[ 'google', 'gemini-image-test' ],
				[ 'openai', 'gpt-image-test' ],
			];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_vision_models' ) ) {
		/** Stub: returns a fixed baseline vision model list for unit tests. */
		function get_preferred_vision_models(): array {
			return [
				[ 'anthropic', 'claude-test' ],
				[ 'google', 'gemini-test' ],
				[ 'openai', 'gpt-test' ],
			];
		}
	}
}

namespace {

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', '/' );
	}

	if ( ! defined( 'DAY_IN_SECONDS' ) ) {
		define( 'DAY_IN_SECONDS', 86400 );
	}

	if ( ! function_exists( 'plugin_basename' ) ) {
		function plugin_basename( string $file ): string {
			return basename( dirname( $file ) ) . '/' . basename( $file );
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter(): void {}
	}

	if ( ! function_exists( 'remove_filter' ) ) {
		function remove_filter(): bool { return true; }
	}

	if ( ! function_exists( 'has_filter' ) ) {
		function has_filter(): bool { return false; }
	}

	if ( ! function_exists( 'add_action' ) ) {
		function add_action(): void {}
	}

	if ( ! function_exists( 'admin_url' ) ) {
		function admin_url( string $path = '' ): string {
			return 'http://example.com/wp-admin/' . ltrim( $path, '/' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( string $url ): string {
			return $url;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( string $text, string $domain = 'default' ): string {
			return $text;
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( string $transient ): mixed {
			return $GLOBALS['_test_transients'][ $transient ] ?? false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( string $transient, mixed $value, int $expiration = 0 ): bool {
			$GLOBALS['_test_transients'][ $transient ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( string $transient ): bool {
			unset( $GLOBALS['_test_transients'][ $transient ] );
			return true;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( string $hook_name, mixed $value, mixed ...$args ): mixed {
			return $value;
		}
	}

	if ( ! function_exists( 'wp_parse_args' ) ) {
		function wp_parse_args( array|string|object $args, array $defaults = [] ): array {
			if ( is_object( $args ) ) {
				$args = get_object_vars( $args );
			} elseif ( is_string( $args ) ) {
				parse_str( $args, $args );
			}
			return array_merge( (array) $defaults, (array) $args );
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( string $option, mixed $default_value = false ): mixed {
			return $GLOBALS['_test_wp_options'][ $option ] ?? $default_value;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( string $option, mixed $value ): bool {
			$GLOBALS['_test_wp_options'][ $option ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( string $key ): string {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/i', '', $key ) );
		}
	}

}
