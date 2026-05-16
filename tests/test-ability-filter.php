<?php
/**
 * Inhale_Ability_Filter tests.
 *
 * @package Inhale_MCP_Abilities
 */

/**
 * Ability filter unit tests.
 */
class Test_Inhale_Ability_Filter extends WP_UnitTestCase {

	/**
	 * Reset the option between cases.
	 */
	public function set_up() {
		parent::set_up();
		delete_option( INHALE_OPTION_NAME );
	}

	/**
	 * Empty option exposes nothing.
	 */
	public function test_empty_option_exposes_nothing() {
		$filter = new Inhale_Ability_Filter();
		$args   = array( 'label' => 'X' );
		$out    = $filter->maybe_expose( $args, 'core/get-posts' );
		$this->assertArrayNotHasKey( 'meta', $out );
	}

	/**
	 * Inhaled ability receives meta.mcp.public=true.
	 */
	public function test_inhaled_ability_gets_public_meta() {
		update_option( INHALE_OPTION_NAME, array( 'core/get-posts' ) );
		$filter = new Inhale_Ability_Filter();
		$out    = $filter->maybe_expose( array( 'label' => 'X' ), 'core/get-posts' );
		$this->assertTrue( $out['meta']['mcp']['public'] );
	}

	/**
	 * Non-inhaled ability is left unchanged.
	 */
	public function test_non_inhaled_ability_is_unchanged() {
		update_option( INHALE_OPTION_NAME, array( 'core/get-posts' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array( 'label' => 'X', 'description' => 'Y' );
		$out    = $filter->maybe_expose( $args, 'core/get-pages' );
		$this->assertSame( $args, $out );
	}

	/**
	 * Abilities in mcp-adapter/ namespace skip the filter entirely.
	 */
	public function test_mcp_adapter_namespace_is_skipped() {
		update_option( INHALE_OPTION_NAME, array( 'mcp-adapter/discover-abilities' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array( 'label' => 'X' );
		$out    = $filter->maybe_expose( $args, 'mcp-adapter/discover-abilities' );
		$this->assertSame( $args, $out );
	}

	/**
	 * Existing meta is preserved when adding the mcp.public flag.
	 */
	public function test_existing_meta_is_preserved() {
		update_option( INHALE_OPTION_NAME, array( 'core/get-posts' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array(
			'label' => 'X',
			'meta'  => array(
				'readonly' => true,
				'mcp'      => array(
					'priority' => 5,
				),
			),
		);
		$out = $filter->maybe_expose( $args, 'core/get-posts' );
		$this->assertTrue( $out['meta']['readonly'] );
		$this->assertSame( 5, $out['meta']['mcp']['priority'] );
		$this->assertTrue( $out['meta']['mcp']['public'] );
	}

	/**
	 * A destructive ability still gets exposed when inhaled. The destructive
	 * confirmation is a UX guard at the admin layer, not a registration-time
	 * filter behavior.
	 */
	public function test_destructive_ability_still_gets_exposed() {
		update_option( INHALE_OPTION_NAME, array( 'core/update-post' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array(
			'label' => 'X',
			'meta'  => array(
				'annotations' => array( 'destructiveHint' => true ),
			),
		);
		$out = $filter->maybe_expose( $args, 'core/update-post' );
		$this->assertTrue( $out['meta']['mcp']['public'] );
		$this->assertTrue( $out['meta']['annotations']['destructiveHint'] );
	}
}
