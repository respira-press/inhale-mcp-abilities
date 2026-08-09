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
	 * Empty option exposes nothing, and says so explicitly.
	 *
	 * This used to assert that no `meta` key was written at all. That was the
	 * right assertion while `meta.mcp` was the only thing a client read, and it
	 * is the wrong one from WordPress 7.1: silence now means "fall through to
	 * the author's meta.public", which is the opposite of what an empty
	 * selection means to the administrator who made it.
	 */
	public function test_empty_option_exposes_nothing() {
		$filter = new Inhale_Ability_Filter();
		$args   = array( 'label' => 'X' );
		$out    = $filter->maybe_expose( $args, 'core/get-posts' );
		$this->assertFalse( $out['meta']['mcp']['public'] );
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
	 * A non-inhaled ability is opted out explicitly, not merely left alone.
	 */
	public function test_non_inhaled_ability_is_opted_out() {
		update_option( INHALE_OPTION_NAME, array( 'core/get-posts' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array( 'label' => 'X', 'description' => 'Y' );
		$out    = $filter->maybe_expose( $args, 'core/get-pages' );
		$this->assertFalse( $out['meta']['mcp']['public'] );
		$this->assertSame( 'X', $out['label'] );
		$this->assertSame( 'Y', $out['description'] );
	}

	/**
	 * The regression this plugin exists to prevent, in one test.
	 *
	 * WordPress 7.1 resolves exposure as
	 * `$meta[ $channel ]['public'] ?? $meta['public'] ?? false`. An ability
	 * whose author set `public => true`, which the administrator did NOT
	 * inhale, would fall through to the author's value and be exposed. The
	 * whole promise of this plugin is that the administrator decides, so the
	 * channel opt-out has to be written explicitly to win the null-coalesce.
	 *
	 * @see https://make.wordpress.org/core/2026/08/04/a-unified-public-exposure-flag-for-abilities-in-wordpress-7-1/
	 */
	public function test_author_public_does_not_bypass_the_administrator() {
		update_option( INHALE_OPTION_NAME, array( 'core/get-posts' ) );
		$filter = new Inhale_Ability_Filter();
		$args   = array(
			'label' => 'X',
			'meta'  => array( 'public' => true ),
		);
		$out = $filter->maybe_expose( $args, 'some-plugin/not-inhaled' );

		$this->assertFalse( $out['meta']['mcp']['public'], 'the channel opt-out must be explicit' );
		// The author's general intent is preserved. Overwriting it would be
		// this plugin editing someone else's registration beyond its remit.
		$this->assertTrue( $out['meta']['public'], "the author's meta.public is left alone" );
	}

	/**
	 * An inhaled ability whose author opted out generally is still exposed:
	 * the administrator's explicit choice is the more specific signal.
	 */
	public function test_inhaled_ability_wins_over_author_opt_out() {
		update_option( INHALE_OPTION_NAME, array( 'some-plugin/inhaled' ) );
		$filter = new Inhale_Ability_Filter();
		$out    = $filter->maybe_expose(
			array( 'meta' => array( 'public' => false ) ),
			'some-plugin/inhaled'
		);
		$this->assertTrue( $out['meta']['mcp']['public'] );
	}

	/**
	 * Unrelated meta survives. The filter writes one key and touches nothing
	 * else, including another integration's channel block.
	 */
	public function test_other_meta_is_preserved() {
		update_option( INHALE_OPTION_NAME, array() );
		$filter = new Inhale_Ability_Filter();
		$out    = $filter->maybe_expose(
			array(
				'meta' => array(
					'show_in_rest' => true,
					'some_client'  => array( 'public' => true ),
					'mcp'          => array( 'annotations' => array( 'readOnly' => true ) ),
				),
			),
			'some-plugin/thing'
		);
		$this->assertTrue( $out['meta']['show_in_rest'] );
		$this->assertTrue( $out['meta']['some_client']['public'] );
		$this->assertTrue( $out['meta']['mcp']['annotations']['readOnly'] );
		$this->assertFalse( $out['meta']['mcp']['public'] );
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
