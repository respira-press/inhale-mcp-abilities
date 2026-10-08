<?php
/**
 * Inhale: MCP Abilities by Respira — uninstall handler.
 *
 * Fires when the site administrator deletes the plugin from the wp-admin
 * Plugins screen. Removes every option this plugin has ever written so a
 * fresh install starts clean and no orphaned data remains in wp_options.
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// v0.4.0 primary option key (plugin-prefixed).
delete_option( 'respira_inhale_public_abilities' );

// v0.4.0 migration flag set by respira_inhale_migrate_options().
delete_option( 'respira_inhale_option_migrated_v040' );

// Canonical compat key proposed in WordPress/mcp-adapter#184 and mirrored
// by this plugin since v0.2.0. Deleted here so the plugin leaves no
// orphan options behind after uninstall.
delete_option( 'mcp_adapter_public_abilities' );

// Legacy v0.1.x option key, replaced by the canonical key in v0.2.0.
// Deleted in case a site is uninstalling on an older version that never
// migrated.
delete_option( 'inhale_mcp_abilities_public_abilities' );

// Legacy v0.2.0 migration flag, superseded by the v0.4.0 flag above.
delete_option( 'inhale_option_migrated_v020' );
delete_option( 'respira_inhale_first_saved_at' );

// Multisite: clean the same options on every blog if the plugin was
// network-installed.
if ( function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_sites' ) ) {
	$respira_inhale_sites = get_sites( array( 'fields' => 'ids' ) );
	if ( is_array( $respira_inhale_sites ) ) {
		foreach ( $respira_inhale_sites as $respira_inhale_site_id ) {
			switch_to_blog( (int) $respira_inhale_site_id );
			delete_option( 'respira_inhale_public_abilities' );
			delete_option( 'respira_inhale_option_migrated_v040' );
			delete_option( 'mcp_adapter_public_abilities' );
			delete_option( 'inhale_mcp_abilities_public_abilities' );
			delete_option( 'inhale_option_migrated_v020' );
			delete_option( 'respira_inhale_first_saved_at' );
			restore_current_blog();
		}
	}
}

// The rating request's per-user dismissal.
delete_metadata( 'user', 0, 'respira_inhale_review_dismissed', '', true );

// The free Respira account connection is shared with Respira ARC. Leave it in
// place while ARC is installed; otherwise revoke the token on respira.press and
// remove it here.
if ( ! file_exists( WP_PLUGIN_DIR . '/respira-arc/respira-arc.php' ) ) {
	$respira_inhale_conn = get_option( 'respira_free_connection' );
	if ( is_array( $respira_inhale_conn ) && ! empty( $respira_inhale_conn['token'] ) ) {
		wp_remote_post(
			'https://www.respira.press/api/v1/free-connect/disconnect',
			array(
				'timeout'  => 5,
				'blocking' => false,
				'headers'  => array( 'Authorization' => 'Bearer ' . $respira_inhale_conn['token'] ),
			)
		);
	}
	delete_option( 'respira_free_connection' );
	delete_option( 'respira_free_site_check' );
	wp_clear_scheduled_hook( 'respira_free_site_check' );
}
