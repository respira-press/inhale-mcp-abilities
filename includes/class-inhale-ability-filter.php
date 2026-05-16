<?php
/**
 * Filters `wp_register_ability_args` to gate MCP visibility based on the
 * saved Inhale option.
 *
 * This is the canonical pattern documented by Weston Ruter:
 * https://weston.ruter.net/2026/04/08/adding-an-mcp-server-to-the-wordpress-core-development-environment/
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inhale_Ability_Filter: opts ability args into meta.mcp.public when the
 * ability is in the saved option.
 */
class Inhale_Ability_Filter {

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

		$exposed = get_option( INHALE_OPTION_NAME, array() );
		if ( ! is_array( $exposed ) ) {
			return $args;
		}

		if ( in_array( $ability_name, $exposed, true ) ) {
			if ( ! isset( $args['meta'] ) || ! is_array( $args['meta'] ) ) {
				$args['meta'] = array();
			}
			if ( ! isset( $args['meta']['mcp'] ) || ! is_array( $args['meta']['mcp'] ) ) {
				$args['meta']['mcp'] = array();
			}
			$args['meta']['mcp']['public'] = true;
		}

		return $args;
	}
}
