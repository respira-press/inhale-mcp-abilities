<?php
/**
 * PHPUnit bootstrap for Inhale: MCP Abilities.
 *
 * Looks for a WP test environment in the conventional locations
 * (WP_TESTS_DIR, /tmp/wordpress-tests-lib). If none is found, defines a
 * minimal shim that lets each test class self-skip with a clear message.
 *
 * @package Inhale_MCP_Abilities
 */

$wp_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $wp_tests_dir ) {
	$wp_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$inhale_test_bootstrap_path = $wp_tests_dir . '/includes/functions.php';

if ( file_exists( $inhale_test_bootstrap_path ) ) {
	require_once $inhale_test_bootstrap_path;

	tests_add_filter(
		'muplugins_loaded',
		static function () {
			require dirname( __DIR__ ) . '/inhale-mcp-abilities.php';
		}
	);

	require $wp_tests_dir . '/includes/bootstrap.php';
} else {
	define( 'INHALE_TESTS_NO_WP', true );

	if ( ! class_exists( 'WP_UnitTestCase' ) ) {
		class WP_UnitTestCase extends PHPUnit\Framework\TestCase { // phpcs:ignore Generic.Classes.OpeningBraceSameLine
			protected function setUp(): void {
				$this->markTestSkipped(
					'WordPress test environment not found. '
					. 'Set WP_TESTS_DIR or run `bash bin/install-wp-tests.sh wp_test root \'\' localhost latest` from the plugin root, then re-run PHPUnit.'
				);
			}
		}
	}
}
