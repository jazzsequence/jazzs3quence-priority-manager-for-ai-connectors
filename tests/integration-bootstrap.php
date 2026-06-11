<?php
/**
 * Bootstrap for integration tests.
 *
 * Requires WP_TESTS_DIR to point to a WordPress test library install.
 * wpunit-helpers sets this up via `composer run test:install`.
 */

// Stub the WordPress AI plugin's connector function — it is not installed in
// the integration test environment. Must match the production namespace.
namespace WordPress\AI {
	if ( ! function_exists( 'WordPress\AI\get_ai_connectors' ) ) {
		function get_ai_connectors( bool $active_only = true ): array {
			return [
				'anthropic' => [ 'name' => 'Anthropic (Claude)' ],
				'google'    => [ 'name' => 'Google (Gemini)' ],
				'openai'    => [ 'name' => 'OpenAI' ],
			];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_models_for_text_generation' ) ) {
		function get_preferred_models_for_text_generation(): array {
			return [
				[ 'anthropic', 'claude-test' ],
				[ 'google', 'gemini-test' ],
				[ 'openai', 'gpt-test' ],
			];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_image_models' ) ) {
		function get_preferred_image_models(): array {
			return [
				[ 'google', 'gemini-image-test' ],
				[ 'openai', 'gpt-image-test' ],
			];
		}
	}

	if ( ! function_exists( 'WordPress\AI\get_preferred_vision_models' ) ) {
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

	$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

	if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
		echo "Could not find WordPress test suite at '{$_tests_dir}'. " .
			"Set WP_TESTS_DIR or run: composer run test:install\n";
		exit( 1 );
	}

	require_once $_tests_dir . '/includes/functions.php';

	function aicp_manually_load_plugin(): void {
		require dirname( __DIR__ ) . '/jazzs3quence-priority-manager-for-ai-connectors.php';
	}
	tests_add_filter( 'muplugins_loaded', 'aicp_manually_load_plugin' );

	// Provide baseline model lists at priority 5, simulating what the WordPress AI
	// plugin would add before our plugin reorders them at priority 10.
	tests_add_filter(
		'wpai_preferred_text_models',
		static fn() => [
			[ 'anthropic', 'claude-test' ],
			[ 'google', 'gemini-test' ],
			[ 'openai', 'gpt-test' ],
		],
		5
	);
	tests_add_filter(
		'wpai_preferred_image_models',
		static fn() => [
			[ 'google', 'gemini-image-test' ],
			[ 'openai', 'gpt-image-test' ],
		],
		5
	);
	tests_add_filter(
		'wpai_preferred_vision_models',
		static fn() => [
			[ 'anthropic', 'claude-test' ],
			[ 'google', 'gemini-test' ],
			[ 'openai', 'gpt-test' ],
		],
		5
	);

	require $_tests_dir . '/includes/bootstrap.php';

}
