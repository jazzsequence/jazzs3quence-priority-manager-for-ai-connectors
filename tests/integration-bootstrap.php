<?php
/**
 * Bootstrap for integration tests.
 *
 * Requires WP_TESTS_DIR to point to a WordPress test library install.
 * wpunit-helpers sets this up via `composer run test:install`.
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find WordPress test suite at '{$_tests_dir}'. " .
		"Set WP_TESTS_DIR or run: composer run test:install\n";
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

function aicp_manually_load_plugin(): void {
	require dirname( __DIR__ ) . '/ai-connector-priority.php';
}
tests_add_filter( 'muplugins_loaded', 'aicp_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
