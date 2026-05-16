<?php
/**
 * Enqueues admin CSS and JS on the Inhale settings page only.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inhale_Assets: scoped asset loader.
 */
class Inhale_Assets {

	const HOOK_SUFFIX = 'settings_page_inhale-mcp-abilities';

	/**
	 * Wire the enqueuer.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 10, 1 );
	}

	/**
	 * Conditional enqueue. Skips every admin page that isn't ours.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public function enqueue( $hook_suffix ) {
		if ( self::HOOK_SUFFIX !== $hook_suffix ) {
			return;
		}

		$min     = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) ? '' : '.min';
		$css_url = INHALE_PLUGIN_URL . 'assets/css/admin' . $min . '.css';
		$js_url  = INHALE_PLUGIN_URL . 'assets/js/admin' . $min . '.js';

		wp_enqueue_style(
			'inhale-mcp-abilities-admin',
			$css_url,
			array(),
			INHALE_VERSION
		);

		wp_enqueue_script(
			'inhale-mcp-abilities-admin',
			$js_url,
			array(),
			INHALE_VERSION,
			true
		);
	}
}
