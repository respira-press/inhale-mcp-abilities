<?php
/**
 * Inhale: MCP Abilities uninstall handler.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'inhale_mcp_abilities_public_abilities' );
