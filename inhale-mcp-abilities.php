<?php
/**
 * Plugin Name: Inhale: MCP Abilities Manager by Respira
 * Plugin URI: https://respira.press/inhale
 * Description: See every ability your plugins give AI agents over MCP, and choose which ones Claude, ChatGPT and other AI clients can use. No code. Built by Respira.
 * Version: 0.7.1
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

/*
 * Respira for WordPress ships its own copy of Inhale under the same class and
 * constant names. On a site running both, exactly one copy may load, and it
 * has to be Respira's: Respira's MCP endpoint, its Abilities screen and its
 * ability proxy are built against its copy and its option
 * (inhale_mcp_abilities_public_abilities).
 *
 * Up to 0.7.0 this file only stood down when it found Respira's classes
 * already declared. Plugins load in alphabetical order, so inhale-mcp-abilities
 * loads before respira-for-wordpress: this copy won, defined
 * RESPIRA_INHALE_OPTION_NAME as its own option, and Respira skipped its copy.
 * Respira's Abilities screen then failed to load and Respira's own tools were
 * hidden from its MCP connection (reported on Respira 9.2.23 + Inhale 0.7.0,
 * 2026-10-27).
 *
 * Since 0.7.1 the decision reads the list of active plugins instead, which is
 * the same whatever the load order:
 *
 * - Respira active (on this site, or network-active on multisite) and its
 *   main file on disk: declare nothing that Respira also declares (no
 *   RESPIRA_INHALE_* constant, no Respira_Inhale_* class, no hook on
 *   wp_register_ability_args, no migration), so Respira's guard loads its
 *   own copy when its turn comes. Show one notice on the Plugins screen.
 * - Respira's classes already declared (Respira loaded first: network-active,
 *   a must-use install, or the request that activates Inhale): the same.
 * - Respira deactivated: it is no longer in the list on the next request, and
 *   this copy loads as normal. Nothing is stored about standing aside.
 * - Respira still listed as active but its folder deleted: file_exists()
 *   fails and this copy loads as normal. WordPress does not load a missing
 *   plugin, so nobody else would provide Inhale.
 * - Respira listed, on disk, but it stopped before loading its copy (its own
 *   PHP version guard, or the full and free builds both active): on
 *   plugins_loaded, when every plugin has loaded, nobody has declared Inhale's
 *   classes, so this copy loads then. See respira_inhale_load_if_unclaimed().
 *
 * Every function in this file sits inside a function_exists() block so that a
 * second copy of this file (another folder) cannot fatal either.
 */

if ( ! function_exists( 'respira_inhale_active_respira_basename' ) ) {
	/**
	 * The plugin basename of an active Respira for WordPress whose main file is
	 * on disk, or an empty string.
	 *
	 * Reads the active plugin lists rather than checking for Respira's classes,
	 * because when this file runs Respira usually has not loaded yet. Matches
	 * the full plugin in any folder (a zip upload can carry the version in the
	 * folder name) and the free build, respira/respira.php. Both bundle Inhale.
	 *
	 * @return string
	 */
	function respira_inhale_active_respira_basename() {
		$active = get_option( 'active_plugins', array() );
		$active = is_array( $active ) ? array_values( $active ) : array();

		if ( is_multisite() ) {
			$network = get_site_option( 'active_sitewide_plugins', array() );
			if ( is_array( $network ) ) {
				$active = array_merge( $active, array_keys( $network ) );
			}
		}

		foreach ( $active as $basename ) {
			if ( ! is_string( $basename ) || '' === $basename ) {
				continue;
			}
			if ( 'respira-for-wordpress.php' !== basename( $basename ) && 'respira/respira.php' !== $basename ) {
				continue;
			}
			if ( 0 !== validate_file( $basename ) ) {
				continue;
			}
			// A stale entry for a deleted folder: WordPress will not load it,
			// so it provides nothing.
			if ( file_exists( WP_PLUGIN_DIR . '/' . $basename ) ) {
				return $basename;
			}
		}

		return '';
	}
}

if ( ! function_exists( 'respira_inhale_already_declared' ) ) {
	/**
	 * Whether another copy of Inhale (Respira's, in practice) has declared the
	 * shared classes in this request.
	 *
	 * Inhale_Plugin is the class name of the earliest Inhale releases, which
	 * Respira's guard also checks for.
	 *
	 * @return bool
	 */
	function respira_inhale_already_declared() {
		return class_exists( 'Respira_Inhale_Plugin', false )
			|| class_exists( 'Respira_Inhale_I18n', false )
			|| class_exists( 'Inhale_Plugin', false );
	}
}

if ( ! function_exists( 'respira_inhale_standing_aside' ) ) {
	/**
	 * Whether this copy is standing aside for Respira in this request.
	 *
	 * @param bool|null $set New value, or null to read.
	 * @return bool
	 */
	function respira_inhale_standing_aside( $set = null ) {
		static $standing_aside = false;
		if ( null !== $set ) {
			$standing_aside = (bool) $set;
		}
		return $standing_aside;
	}
}

if ( ! function_exists( 'respira_inhale_load' ) ) {
	/**
	 * Define this copy's constants and load its classes.
	 *
	 * Only ever called when no other copy of Inhale is loaded.
	 */
	function respira_inhale_load() {
		$plugin_file = __FILE__;

		// Plugin-prefixed constants. The `respira_inhale_` prefix is unique to
		// this plugin and does not collide with the wider `respira_` prefix
		// used by the main Respira for WordPress plugin.
		if ( ! defined( 'RESPIRA_INHALE_VERSION' ) ) {
			define( 'RESPIRA_INHALE_VERSION', '0.7.1' );
		}
		if ( ! defined( 'RESPIRA_INHALE_PLUGIN_FILE' ) ) {
			define( 'RESPIRA_INHALE_PLUGIN_FILE', $plugin_file );
		}
		if ( ! defined( 'RESPIRA_INHALE_PLUGIN_DIR' ) ) {
			define( 'RESPIRA_INHALE_PLUGIN_DIR', plugin_dir_path( $plugin_file ) );
		}
		if ( ! defined( 'RESPIRA_INHALE_PLUGIN_URL' ) ) {
			define( 'RESPIRA_INHALE_PLUGIN_URL', plugin_dir_url( $plugin_file ) );
		}

		// Primary option key used by this plugin. Properly prefixed so it
		// cannot collide with options from any other plugin.
		if ( ! defined( 'RESPIRA_INHALE_OPTION_NAME' ) ) {
			define( 'RESPIRA_INHALE_OPTION_NAME', 'respira_inhale_public_abilities' );
		}

		// Compatibility option key proposed first-party in
		// WordPress/mcp-adapter PR #184. The plugin mirrors writes to this key
		// and falls back to reading it so that, if the upstream adapter ever
		// ships its own settings UI under this name, both surfaces share
		// state. Read/write through this key is documented and intentional.
		if ( ! defined( 'RESPIRA_INHALE_COMPAT_OPTION_NAME' ) ) {
			define( 'RESPIRA_INHALE_COMPAT_OPTION_NAME', 'mcp_adapter_public_abilities' );
		}

		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-i18n.php';
		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-ability-filter.php';
		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-settings-page.php';
		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-assets.php';
		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-free-connect.php';
		require_once RESPIRA_INHALE_PLUGIN_DIR . 'includes/class-respira-inhale-plugin.php';
	}
}

if ( ! function_exists( 'respira_inhale_migrate_options' ) ) {
	/**
	 * One-shot migration that runs on `plugins_loaded` priority 5, before
	 * Respira_Inhale_Plugin boots. Copies any saved selection from the
	 * v0.2.x-v0.3.x canonical-shared key or the v0.1.x key onto the v0.4.0
	 * prefixed primary key when the primary key is empty.
	 *
	 * The v0.1.x key, inhale_mcp_abilities_public_abilities, is also the live
	 * list of Respira for WordPress's bundled Inhale. Up to 0.7.0 this
	 * migration deleted it after copying, which removed Respira's list on a
	 * site running both plugins. Since 0.7.1 it is read and never written or
	 * deleted. It does not run at all while this copy stands aside for
	 * Respira.
	 */
	function respira_inhale_migrate_options() {
		if ( get_option( 'respira_inhale_option_migrated_v040' ) ) {
			return;
		}

		$primary_key      = RESPIRA_INHALE_OPTION_NAME;
		$canonical_key    = RESPIRA_INHALE_COMPAT_OPTION_NAME;      // v0.2.x to v0.3.x
		$legacy_key       = 'inhale_mcp_abilities_public_abilities'; // v0.1.x, and Respira's live list: read only.
		$legacy_flag_v0_2 = 'inhale_option_migrated_v020';           // v0.2.0 one-shot flag

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
		delete_option( $legacy_flag_v0_2 );

		update_option( 'respira_inhale_option_migrated_v040', 1, false );
	}
}

if ( ! function_exists( 'respira_inhale_load_if_unclaimed' ) ) {
	/**
	 * On plugins_loaded, after every plugin file has run: if this copy stood
	 * aside for Respira but Respira never declared its copy of Inhale (it
	 * stopped early on purpose, for example on an unsupported PHP version or
	 * because both Respira builds are active), load this copy after all, so
	 * the site is not left without one.
	 */
	function respira_inhale_load_if_unclaimed() {
		if ( ! respira_inhale_standing_aside() ) {
			return;
		}
		if ( respira_inhale_already_declared() || defined( 'RESPIRA_INHALE_OPTION_NAME' ) ) {
			return;
		}

		respira_inhale_standing_aside( false );
		respira_inhale_load();
		respira_inhale_migrate_options();
		Respira_Inhale_Plugin::instance();
	}
}

if ( ! function_exists( 'respira_inhale_standing_aside_notice' ) ) {
	/**
	 * One line on the Plugins screen while this copy stands aside.
	 */
	function respira_inhale_standing_aside_notice() {
		if ( ! respira_inhale_standing_aside() || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! is_object( $screen ) || ! isset( $screen->id ) || ! in_array( $screen->id, array( 'plugins', 'plugins-network' ), true ) ) {
			return;
		}
		echo '<div class="notice notice-info"><p>';
		echo esc_html__(
			'Respira for WordPress is active and already includes Inhale, so the Inhale plugin is standing aside and Respira\'s copy is in charge. Choose abilities in Respira, under Abilities. You can deactivate the Inhale plugin.',
			'inhale-mcp-abilities'
		);
		echo '</p></div>';
	}
}

if ( respira_inhale_already_declared() || '' !== respira_inhale_active_respira_basename() ) {
	respira_inhale_standing_aside( true );
	add_action( 'plugins_loaded', 'respira_inhale_load_if_unclaimed', 0 );
	add_action( 'admin_notices', 'respira_inhale_standing_aside_notice' );
	add_action( 'network_admin_notices', 'respira_inhale_standing_aside_notice' );
	return;
}

respira_inhale_load();

add_action( 'plugins_loaded', 'respira_inhale_migrate_options', 5 );
add_action(
	'plugins_loaded',
	static function () {
		Respira_Inhale_Plugin::instance();
	}
);
