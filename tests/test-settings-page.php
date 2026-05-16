<?php
/**
 * Inhale_Settings_Page tests.
 *
 * @package Inhale_MCP_Abilities
 */

/**
 * Settings page integration tests.
 */
class Test_Inhale_Settings_Page extends WP_UnitTestCase {

	/**
	 * Menu registers under Settings.
	 */
	public function test_menu_is_registered() {
		global $submenu;

		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );

		do_action( 'admin_menu' );

		$slugs = array();
		if ( isset( $submenu['options-general.php'] ) ) {
			foreach ( $submenu['options-general.php'] as $item ) {
				$slugs[] = $item[2];
			}
		}
		$this->assertContains( 'inhale-mcp-abilities', $slugs );
	}

	/**
	 * Non-admin users cannot reach the settings page render.
	 */
	public function test_non_admin_cannot_access_page() {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		wp_set_current_user( $subscriber );

		$page = new Inhale_Settings_Page();

		$this->expectException( WPDieException::class );
		$page->render_page();
	}

	/**
	 * Sanitize callback accepts known ability names.
	 */
	public function test_option_sanitize_accepts_known_abilities() {
		$page = $this->getMockBuilder( 'Inhale_Settings_Page' )
			->onlyMethods( array( 'discover_abilities' ) )
			->getMock();
		$page->method( 'discover_abilities' )->willReturn(
			array(
				array( 'name' => 'core/get-posts' ),
				array( 'name' => 'core/update-post' ),
			)
		);

		$out = $page->sanitize_option( array( 'core/get-posts', 'core/update-post' ) );
		$this->assertSame( array( 'core/get-posts', 'core/update-post' ), $out );
	}

	/**
	 * Sanitize callback rejects unknown strings.
	 */
	public function test_option_sanitize_rejects_unknown_strings() {
		$page = $this->getMockBuilder( 'Inhale_Settings_Page' )
			->onlyMethods( array( 'discover_abilities' ) )
			->getMock();
		$page->method( 'discover_abilities' )->willReturn(
			array(
				array( 'name' => 'core/get-posts' ),
			)
		);

		$out = $page->sanitize_option( array( 'core/get-posts', 'pwned/exfil', '<script>', '' ) );
		$this->assertSame( array( 'core/get-posts' ), $out );
	}

	/**
	 * Sanitize callback drops mcp-adapter/ entries.
	 */
	public function test_mcp_adapter_abilities_are_excluded() {
		$page = $this->getMockBuilder( 'Inhale_Settings_Page' )
			->onlyMethods( array( 'discover_abilities' ) )
			->getMock();
		$page->method( 'discover_abilities' )->willReturn(
			array(
				array( 'name' => 'mcp-adapter/discover-abilities' ),
				array( 'name' => 'core/get-posts' ),
			)
		);

		$out = $page->sanitize_option( array( 'mcp-adapter/discover-abilities', 'core/get-posts' ) );
		$this->assertSame( array( 'core/get-posts' ), $out );
	}

	/**
	 * Plugin action link is added on the Plugins screen.
	 */
	public function test_plugin_action_link_appears() {
		$plugin = Inhale_Plugin::instance();
		$links  = $plugin->add_settings_action_link( array( '<a>Deactivate</a>' ) );
		$this->assertCount( 2, $links );
		$this->assertStringContainsString( 'page=inhale-mcp-abilities', $links[0] );
	}
}
