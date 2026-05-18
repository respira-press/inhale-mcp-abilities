<?php
/**
 * Plugin Name: Inhale: MCP Abilities
 * Plugin URI: https://respira.press/inhale
 * Description: Decide which registered WordPress abilities are visible to the default MCP server. A deliberate, considered way to expose abilities without writing PHP.
 * Version: 0.3.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author: Respira
 * Author URI: https://respira.press
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inhale-mcp-abilities
 * Domain Path: /languages
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'INHALE_VERSION', '0.3.0' );
define( 'INHALE_PLUGIN_FILE', __FILE__ );
define( 'INHALE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INHALE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Canonical option key shared with WordPress/mcp-adapter PR #184.
// When the adapter eventually ships its own settings UI, both surfaces
// read and write the same option so users see one consistent state.
define( 'INHALE_OPTION_NAME', 'mcp_adapter_public_abilities' );

/**
 * One-shot migration from the v0.1.x Inhale-prefixed option name to the
 * canonical mcp_adapter_public_abilities key. Runs at plugins_loaded
 * priority 5, before Inhale_Plugin boots, so the rest of the plugin
 * sees the migrated state.
 */
function inhale_maybe_migrate_option_v020() {
	if ( get_option( 'inhale_option_migrated_v020' ) ) {
		return;
	}
	$legacy_key = 'inhale_mcp_abilities_public_abilities';
	$legacy     = get_option( $legacy_key, null );
	$current    = get_option( INHALE_OPTION_NAME, null );
	if ( is_array( $legacy ) && ! empty( $legacy ) && empty( $current ) ) {
		update_option( INHALE_OPTION_NAME, array_values( array_unique( $legacy ) ) );
	}
	if ( null !== $legacy ) {
		delete_option( $legacy_key );
	}
	update_option( 'inhale_option_migrated_v020', 1, false );
}
add_action( 'plugins_loaded', 'inhale_maybe_migrate_option_v020', 5 );

require_once INHALE_PLUGIN_DIR . 'includes/class-inhale-i18n.php';
require_once INHALE_PLUGIN_DIR . 'includes/class-inhale-ability-filter.php';
require_once INHALE_PLUGIN_DIR . 'includes/class-inhale-settings-page.php';
require_once INHALE_PLUGIN_DIR . 'includes/class-inhale-assets.php';
require_once INHALE_PLUGIN_DIR . 'includes/class-inhale-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		Inhale_Plugin::instance();
	}
);
