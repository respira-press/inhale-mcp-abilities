<?php
/**
 * Filters `wp_register_ability_args` to gate MCP visibility based on the
 * saved Inhale option.
 *
 * This is the canonical pattern documented by Weston Ruter:
 * https://weston.ruter.net/2026/04/08/adding-an-mcp-server-to-the-wordpress-core-development-environment/
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Respira_Inhale_Ability_Filter: opts ability args into meta.mcp.public when
 * the ability is in the saved option.
 */
class Respira_Inhale_Ability_Filter {

	/**
	 * Wire the filter.
	 */
	public function __construct() {
		add_filter( 'wp_register_ability_args', array( $this, 'maybe_expose' ), 10, 2 );
	}

	/**
	 * Add `meta.mcp.public = true` to abilities the admin has inhaled.
	 *
	 * Abilities registered under the `mcp-adapter/` namespace are skipped:
	 * the adapter manages its own surface.
	 *
	 * Reads from the plugin-prefixed primary option first; if that is
	 * empty, falls back to the canonical compat key proposed in
	 * WordPress/mcp-adapter#184 so that, if the upstream adapter UI
	 * ever ships and writes to that key, the filter still honors the
	 * administrator's selection.
	 *
	 * @param array  $args         Ability registration args.
	 * @param string $ability_name The ability name being registered.
	 * @return array
	 */
	public function maybe_expose( $args, $ability_name ) {
		if ( ! is_array( $args ) ) {
			return $args;
		}

		if ( ! is_string( $ability_name ) ) {
			return $args;
		}

		if ( 0 === strpos( $ability_name, 'mcp-adapter/' ) ) {
			return $args;
		}

		$exposed = get_option( RESPIRA_INHALE_OPTION_NAME, array() );
		if ( ! is_array( $exposed ) || empty( $exposed ) ) {
			$compat = get_option( RESPIRA_INHALE_COMPAT_OPTION_NAME, array() );
			if ( is_array( $compat ) && ! empty( $compat ) ) {
				$exposed = $compat;
			}
		}

		if ( ! is_array( $exposed ) ) {
			return $args;
		}

		if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
			$args['meta'] = array();
		}
		if ( ! isset( $args['meta']['mcp'] ) || ! is_array( $args['meta']['mcp'] ) ) {
			$args['meta']['mcp'] = array();
		}

		// Decide in BOTH directions, which is new as of WordPress 7.1.
		//
		// This filter used to set mcp.public = true on an inhaled ability and
		// leave everything else untouched. That was correct while `meta.mcp`
		// was the only thing an MCP client read. WordPress 7.1 adds a unified
		// `meta.public` flag, and integrations resolve exposure as:
		//
		//     $meta[ $channel ]['public'] ?? $meta['public'] ?? false
		//
		// So an ability the administrator did NOT inhale, whose author set
		// `public => true`, now falls through to the author's value and gets
		// exposed. That silently inverts the promise this plugin makes: the
		// administrator chooses what is reachable, not the plugin author.
		//
		// Writing an explicit false closes it. The core resolution uses
		// null-coalescing, so an explicit false is preserved and is not
		// treated as a missing value; only null falls through. The dev note
		// asks integrations not to override an explicit channel opt-out
		// because `public` is true, and this IS that channel opt-out, set on
		// the administrator's behalf.
		//
		// @see https://make.wordpress.org/core/2026/08/04/a-unified-public-exposure-flag-for-abilities-in-wordpress-7-1/
		$args['meta']['mcp']['public'] = in_array( $ability_name, $exposed, true );

		return $args;
	}
}
