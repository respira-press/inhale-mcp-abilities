<?php
/**
 * Enqueues admin CSS and JS on the Inhale settings page only.
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Respira_Inhale_Assets: scoped asset loader.
 */
class Respira_Inhale_Assets {

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

		// Single source-of-truth assets. The plugin admin surface is
		// small and loads on one settings page only; shipping a separate
		// minified bundle would just duplicate the same code under a
		// different name without a meaningful payload reduction, and
		// the WordPress Plugin Directory prefers human-readable code.
		wp_enqueue_style(
			'inhale-mcp-abilities-admin',
			RESPIRA_INHALE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			RESPIRA_INHALE_VERSION
		);

		wp_enqueue_script(
			'inhale-mcp-abilities-admin',
			RESPIRA_INHALE_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			RESPIRA_INHALE_VERSION,
			true
		);
	}
}
