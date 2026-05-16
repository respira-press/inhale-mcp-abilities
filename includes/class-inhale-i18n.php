<?php
/**
 * Loads the inhale-mcp-abilities text domain.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inhale_I18n: text domain loader.
 */
class Inhale_I18n {

	/**
	 * Wire the loader to `init`.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ), 1 );
	}

	/**
	 * Load the plugin text domain.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'inhale-mcp-abilities',
			false,
			dirname( plugin_basename( INHALE_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
