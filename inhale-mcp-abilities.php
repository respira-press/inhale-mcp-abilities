<?php
/**
 * Plugin Name: Inhale: MCP Abilities Manager by Respira
 * Plugin URI: https://respira.press/inhale
 * Description: See every ability your plugins give AI agents over MCP, and choose which ones Claude, ChatGPT and other AI clients can use. No code. Built by Respira.
 * Version: 0.6.0
 * Requires at least: 6.8
 * Requires PHP: 7.4
 * Author: Respira
 * Author URI: https://respira.press
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: inhale-mcp-abilities
 * Domain Path: /languages
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-prefixed constants. The `respira_inhale_` prefix is unique to
// this plugin and does not collide with the wider `respira_` prefix used
// by the main Respira for WordPress plugin.
if ( ! defined( 'RESPIRA_INHALE_VERSION' ) ) {
	define( 'RESPIRA_INHALE_VERSION', '0.6.0' );
}
if ( ! defined( 'RESPIRA_INHALE_PLUGIN_FILE' ) ) {
	define( 'RESPIRA_INHALE_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'RESPIRA_INHALE_PLUGIN_DIR' ) ) {
	define( 'RESPIRA_INHALE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'RESPIRA_INHALE_PLUGIN_URL' ) ) {
	define( 'RESPIRA_INHALE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Primary option key used by this plugin. Properly prefixed so it cannot
// collide with options from any other plugin.
if ( ! defined( 'RESPIRA_INHALE_OPTION_NAME' ) ) {
	define( 'RESPIRA_INHALE_OPTION_NAME', 'respira_inhale_public_abilities' );
}

// Compatibility option key proposed first-party in WordPress/mcp-adapter
// PR #184. The plugin mirrors writes to this key and falls back to
// reading it so that, if the upstream adapter ever ships its own
// settings UI under this name, both surfaces share state. Read/write
// through this key is documented and intentional.
if ( ! defined( 'RESPIRA_INHALE_COMPAT_OPTION_NAME' ) ) {
	define( 'RESPIRA_INHALE_COMPAT_OPTION_NAME', 'mcp_adapter_public_abilities' );
}

/**
 * One-shot migration that runs on `plugins_loaded` priority 5, before
 * Respira_Inhale_Plugin boots. Moves any saved selection from the
 * v0.1.x legacy key or the v0.2.x-v0.3.x canonical-shared key onto the
 * v0.4.0 prefixed primary key, then deletes the old keys and the older
 * migration flag.
 */
function respira_inhale_migrate_options() {
	if ( get_option( 'respira_inhale_option_migrated_v040' ) ) {
		return;
	}

	$primary_key       = RESPIRA_INHALE_OPTION_NAME;
	$canonical_key     = RESPIRA_INHALE_COMPAT_OPTION_NAME;     // v0.2.x to v0.3.x
	$legacy_key        = 'inhale_mcp_abilities_public_abilities'; // v0.1.x
	$legacy_flag_v0_2  = 'inhale_option_migrated_v020';           // v0.2.0 one-shot flag

	$primary = get_option( $primary_key, null );

	if ( null === $primary || ( is_array( $primary ) && empty( $primary ) ) ) {
		$canonical = get_option( $canonical_key, null );
		$legacy    = get_option( $legacy_key, null );

		if ( is_array( $canonical ) && ! empty( $canonical ) ) {
			update_option( $primary_key, array_values( array_unique( $canonical ) ), false );
		} elseif ( is_array( $legacy ) && ! empty( $legacy ) ) {
			update_option( $primary_key, array_values( array_unique( $legacy ) ), false );
		}
	}

	delete_option( $canonical_key );
	delete_option( $legacy_key );
	delete_option( $legacy_flag_v0_2 );

	update_option( 'respira_inhale_option_migrated_v040', 1, false );
}
add_action( 'plugins_loaded', 'respira_inhale_migrate_options', 5 );

/**
 * Stand down when Respira for WordPress has already loaded its bundled copy.
 *
 * Respira ships Inhale inside it, and its loader skips the bundled copy when it
 * sees our classes. That guard only covers one order of events: Inhale first,
 * Respira second. The reverse happens whenever someone who already runs Respira
 * activates Inhale from the directory, which is the ordinary path, since Inhale
 * is how people meet Respira. In that request Respira is already in memory, our
 * require redeclares Respira_Inhale_I18n, and the site fatals on activation.
 *
 * Reproduced on WordPress 7.1-RC3: "Cannot redeclare class Respira_Inhale_I18n".
 *
 * Bailing here rather than wrapping each class in class_exists, because half a
 * plugin loaded against another plugin's copies is a worse outcome than no
 * plugin: the two versions can disagree about option names and hook order.
 * Respira already provides everything this plugin does, so there is nothing for
 * the user to lose.
 */
if ( class_exists( 'Respira_Inhale_Plugin', false ) || class_exists( 'Respira_Inhale_I18n', false ) ) {
	add_action(
		'admin_notices',
		static function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-info"><p>';
			echo esc_html__(
				'Inhale is already included in Respira for WordPress, so this plugin is standing down. You can safely deactivate it; nothing is lost.',
				'inhale-mcp-abilities'
			);
			echo '</p></div>';
		}
	);
	return;
}

require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-i18n.php';
require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-ability-filter.php';
require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-settings-page.php';
require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-assets.php';
require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		Respira_Inhale_Plugin::instance();
	}
);
