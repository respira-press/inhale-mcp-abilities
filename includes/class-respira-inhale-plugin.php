<?php
/**
 * Singleton bootstrap that wires Inhale's pieces together.
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Respira_Inhale_Plugin: top-level glue.
 */
class Respira_Inhale_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Respira_Inhale_Plugin|null
	 */
	private static $instance = null;

	/**
	 * I18n loader.
	 *
	 * @var Respira_Inhale_I18n
	 */
	public $i18n;

	/**
	 * Ability filter (front + admin).
	 *
	 * @var Respira_Inhale_Ability_Filter
	 */
	public $ability_filter;

	/**
	 * Settings page (admin only).
	 *
	 * @var Respira_Inhale_Settings_Page|null
	 */
	public $settings_page = null;

	/**
	 * Asset loader (admin only).
	 *
	 * @var Respira_Inhale_Assets|null
	 */
	public $assets = null;

	/**
	 * Get the singleton.
	 *
	 * @return Respira_Inhale_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {
		$this->i18n           = new Respira_Inhale_I18n();
		$this->ability_filter = new Respira_Inhale_Ability_Filter();

		if ( is_admin() ) {
			$this->settings_page = new Respira_Inhale_Settings_Page();
			$this->assets        = new Respira_Inhale_Assets();

			add_filter(
				'plugin_action_links_' . plugin_basename( RESPIRA_INHALE_PLUGIN_FILE ),
				array( $this, 'add_settings_action_link' ),
				10,
				1
			);

			add_action( 'current_screen', array( $this, 'suppress_foreign_admin_notices' ) );
		}
	}

	/**
	 * On the Inhale settings page only: drop every admin notice queued by
	 * other plugins or the active theme. Inhale's own notices render inline
	 * from Respira_Inhale_Settings_Page::render_notice() and are unaffected.
	 *
	 * Keeps the page focused on the one decision it's meant to support
	 * (which abilities to expose) instead of bleeding through unrelated
	 * license warnings, plugin-install nags and update banners.
	 *
	 * @param WP_Screen $screen Current admin screen.
	 */
	public function suppress_foreign_admin_notices( $screen ) {
		if ( ! ( $screen instanceof WP_Screen ) ) {
			return;
		}
		if ( 'settings_page_' . Respira_Inhale_Settings_Page::MENU_SLUG !== $screen->id ) {
			return;
		}
		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
		remove_all_actions( 'user_admin_notices' );
		remove_all_actions( 'network_admin_notices' );
	}

	/**
	 * Prepend a Settings link to the row on the Plugins screen.
	 *
	 * @param array $links Existing action links.
	 * @return array
	 */
	public function add_settings_action_link( $links ) {
		if ( ! is_array( $links ) ) {
			$links = array();
		}
		$settings_link = '<a href="' . esc_url( admin_url( 'options-general.php?page=inhale-mcp-abilities' ) ) . '">' . esc_html__( 'Settings', 'inhale-mcp-abilities' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}
}
