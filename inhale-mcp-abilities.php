<?php
/**
 * Plugin Name: Inhale: MCP Abilities
 * Plugin URI: https://respira.press/docs/inhale-mcp-abilities/
 * Description: Decide which registered WordPress abilities are visible to the default MCP server. A deliberate, considered way to expose abilities without writing PHP.
 * Version: 0.1.0
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

define( 'INHALE_VERSION', '0.1.0' );
define( 'INHALE_PLUGIN_FILE', __FILE__ );
define( 'INHALE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'INHALE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'INHALE_OPTION_NAME', 'inhale_mcp_abilities_public_abilities' );

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
