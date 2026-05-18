<?php
/**
 * Inhale: MCP Abilities uninstall handler.
 *
 * Fires when the site administrator deletes the plugin from the wp-admin
 * Plugins screen. Removes every option this plugin has ever written so a
 * fresh install starts clean and no orphaned data remains in wp_options.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Canonical option shared with WordPress/mcp-adapter PR #184 (added in v0.2.0).
delete_option( 'mcp_adapter_public_abilities' );

// Legacy option used by v0.1.0 and v0.1.1, replaced by the canonical key in v0.2.0.
// Deleted here in case a site is uninstalling on an older version that never migrated.
delete_option( 'inhale_mcp_abilities_public_abilities' );

// Migration flag set by inhale_maybe_migrate_option_v020() so the one-shot copy runs once.
delete_option( 'inhale_option_migrated_v020' );

// Multisite: clean the same options on every blog if the plugin was network-installed.
if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
	$sites = get_sites( array( 'fields' => 'ids' ) );
	if ( is_array( $sites ) ) {
		foreach ( $sites as $site_id ) {
			switch_to_blog( (int) $site_id );
			delete_option( 'mcp_adapter_public_abilities' );
			delete_option( 'inhale_mcp_abilities_public_abilities' );
			delete_option( 'inhale_option_migrated_v020' );
			restore_current_blog();
		}
	}
}
