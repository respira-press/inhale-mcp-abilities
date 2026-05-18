<?php
/**
 * Text-domain placeholder for the inhale-mcp-abilities plugin.
 *
 * Since WordPress 4.6, translations for plugins hosted on the .org
 * Plugin Directory are loaded automatically by core. This class is
 * kept as a thin placeholder so the bootstrap can still instantiate
 * Inhale_I18n without any side effects.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inhale_I18n: no-op text-domain placeholder.
 */
class Inhale_I18n {

	/**
	 * Empty constructor. WordPress.org auto-loads translations under
	 * the plugin slug, so no manual loader is needed.
	 */
	public function __construct() {
		// Intentionally empty.
	}
}
