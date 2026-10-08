<?php
/**
 * Renders Settings > Inhale MCP Abilities and the Settings API plumbing
 * that backs it.
 *
 * @package Respira_Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Respira_Inhale_Settings_Page: admin menu, option registration, and page render.
 */
class Respira_Inhale_Settings_Page {

	const MENU_SLUG     = 'inhale-mcp-abilities';
	const CAPABILITY    = 'manage_options';
	const MANAGED_NS    = 'mcp-adapter/';
	const DEFAULT_SERVER_REST_PATH = 'mcp/mcp-adapter-default-server';
	const NONCE_ACTION  = 'respira_inhale_apply';

	/**
	 * Cache for the discovered abilities (per request).
	 *
	 * @var array<int, array<string, mixed>>|null
	 */
	private $abilities_cache = null;

	/**
	 * Wire admin hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 10, 0 );
	}

	/**
	 * Register the Settings sub-menu entry.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Inhale: MCP Abilities', 'inhale-mcp-abilities' ),
			__( 'Inhale: MCP Abilities', 'inhale-mcp-abilities' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Process a bulk action POST if one was submitted, then redirect back
	 * to the settings page so the table renders the fresh state and a
	 * success notice. Standard wp-admin list-table flow.
	 *
	 * Called from render_page() before any output.
	 */
	private function maybe_process_bulk_action() {
		// "No thanks" on the rating request: remember it for this user, for good.
		if ( isset( $_GET['inhale_review'], $_GET['_wpnonce'] ) && 'dismiss' === sanitize_key( wp_unslash( $_GET['inhale_review'] ) ) ) {
			if ( current_user_can( self::CAPABILITY ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'respira_inhale_review' ) ) {
				update_user_meta( get_current_user_id(), 'respira_inhale_review_dismissed', 1 );
			}
			return;
		}

		if ( 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : '' ) ) {
			return;
		}
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';
		if ( ! $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return;
		}

		// Bulk action selector. Posts pattern: `action` (top) or
		// `action2` (bottom). If both are -1 ignore.
		$action  = isset( $_POST['action'] ) ? sanitize_key( wp_unslash( $_POST['action'] ) ) : '';
		$action2 = isset( $_POST['action2'] ) ? sanitize_key( wp_unslash( $_POST['action2'] ) ) : '';
		if ( '-1' === $action || '' === $action ) {
			$action = $action2;
		}
		if ( ! in_array( $action, array( 'inhale', 'exhale' ), true ) ) {
			$this->redirect_with_message( 'no_action' );
			return;
		}

		// Sanitize each posted ability name on entry. Nonce + capability
		// were verified above so this is post-auth user input that still
		// must be normalized to safe scalar strings before the registry
		// lookup below.
		$abilities_raw = isset( $_POST['abilities'] ) ? wp_unslash( $_POST['abilities'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per-element below.
		if ( ! is_array( $abilities_raw ) ) {
			$abilities_raw = array();
		}
		$abilities = array_values(
			array_filter(
				array_map(
					static function ( $entry ) {
						return is_string( $entry ) ? sanitize_text_field( $entry ) : '';
					},
					$abilities_raw
				)
			)
		);
		if ( empty( $abilities ) ) {
			$this->redirect_with_message( 'none_selected' );
			return;
		}

		// Validate against currently registered abilities and reject
		// anything in the adapter-managed namespace.
		$known = array_map(
			static function ( $a ) {
				return isset( $a['name'] ) ? (string) $a['name'] : '';
			},
			$this->discover_abilities()
		);
		$known = array_values( array_filter( $known ) );

		$abilities = array_values(
			array_filter(
				$abilities,
				function ( $name ) use ( $known ) {
					if ( 0 === strpos( $name, self::MANAGED_NS ) ) {
						return false;
					}
					return in_array( $name, $known, true );
				}
			)
		);

		if ( empty( $abilities ) ) {
			$this->redirect_with_message( 'none_valid' );
			return;
		}

		$current = $this->get_exposed();

		if ( 'inhale' === $action ) {
			$new_list = array_values( array_unique( array_merge( $current, $abilities ) ) );
		} else {
			$new_list = array_values( array_diff( $current, $abilities ) );
		}

		update_option( RESPIRA_INHALE_OPTION_NAME, $new_list );
		// The rating request waits a week from the first saved choice.
		if ( ! get_option( 'respira_inhale_first_saved_at' ) ) {
			add_option( 'respira_inhale_first_saved_at', time(), '', false );
		}

		// Write-through to the canonical compat key proposed first-party in
		// WordPress/mcp-adapter#184. If that PR ever merges and the upstream
		// adapter ships its own settings UI under this key, both surfaces
		// will see the same selection state with no migration required.
		update_option( RESPIRA_INHALE_COMPAT_OPTION_NAME, $new_list, false );

		$this->redirect_with_message(
			'inhale' === $action ? 'inhaled' : 'exhaled',
			count( $abilities )
		);
	}

	/**
	 * Redirect back to the settings page with a notice query param.
	 *
	 * @param string   $code  Notice code.
	 * @param int|null $count Optional count for the notice.
	 */
	private function redirect_with_message( $code, $count = null ) {
		$url = admin_url( 'options-general.php?page=' . self::MENU_SLUG );
		$url = add_query_arg( 'notice', sanitize_key( $code ), $url );
		if ( null !== $count ) {
			$url = add_query_arg( 'notice_count', (int) $count, $url );
		}
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Render any success/info notice triggered by the previous request.
	 */
	private function render_notice() {
		// Read-only display path: render a confirmation banner after
		// a redirect from a nonce-verified POST. The notice code is
		// matched against a hardcoded whitelist below, and the count
		// is cast to int. No state change here, so no nonce required.
		if ( ! isset( $_GET['notice'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display-only post-redirect-get banner.
			return;
		}
		$code  = sanitize_key( wp_unslash( $_GET['notice'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- whitelisted in switch below.
		$count = isset( $_GET['notice_count'] ) ? (int) $_GET['notice_count'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- int cast, display-only.

		switch ( $code ) {
			case 'inhaled':
				$message = sprintf(
					/* translators: %d: count of abilities inhaled. */
					_n( '%d ability inhaled.', '%d abilities inhaled.', max( 1, $count ), 'inhale-mcp-abilities' ),
					max( 1, $count )
				);
				$type = 'success';
				break;
			case 'exhaled':
				$message = sprintf(
					/* translators: %d: count of abilities exhaled. */
					_n( '%d ability exhaled.', '%d abilities exhaled.', max( 1, $count ), 'inhale-mcp-abilities' ),
					max( 1, $count )
				);
				$type = 'success';
				break;
			case 'no_action':
				$message = __( 'Choose a bulk action before clicking Apply.', 'inhale-mcp-abilities' );
				$type    = 'warning';
				break;
			case 'none_selected':
				$message = __( 'Select at least one ability before applying a bulk action.', 'inhale-mcp-abilities' );
				$type    = 'warning';
				break;
			case 'none_valid':
				$message = __( 'No valid abilities to act on.', 'inhale-mcp-abilities' );
				$type    = 'warning';
				break;
			default:
				return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible inhale-notice"><p>%s</p></div>',
			esc_attr( 'success' === $type ? 'success' : 'warning' ),
			esc_html( $message )
		);
	}

	/**
	 * Discover registered abilities on this site.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function discover_abilities() {
		if ( null !== $this->abilities_cache ) {
			return $this->abilities_cache;
		}

		$rows = array();

		if ( function_exists( 'wp_get_abilities' ) ) {
			$abilities = call_user_func( 'wp_get_abilities' );
			if ( is_array( $abilities ) ) {
				foreach ( $abilities as $ability ) {
					$row = $this->normalize_ability( $ability );
					if ( null !== $row ) {
						$rows[] = $row;
					}
				}
			}
		}

		$this->abilities_cache = $rows;
		return $rows;
	}

	/**
	 * Normalize an ability instance/array into the row shape this page
	 * renders against.
	 *
	 * @param mixed $ability Ability instance returned by wp_get_abilities().
	 * @return array<string, mixed>|null
	 */
	private function normalize_ability( $ability ) {
		$name        = '';
		$label       = '';
		$description = '';
		$meta        = array();

		if ( is_object( $ability ) ) {
			if ( method_exists( $ability, 'get_name' ) ) {
				$name = (string) $ability->get_name();
			} elseif ( isset( $ability->name ) ) {
				$name = (string) $ability->name;
			}
			if ( method_exists( $ability, 'get_label' ) ) {
				$label = (string) $ability->get_label();
			} elseif ( isset( $ability->label ) ) {
				$label = (string) $ability->label;
			}
			if ( method_exists( $ability, 'get_description' ) ) {
				$description = (string) $ability->get_description();
			} elseif ( isset( $ability->description ) ) {
				$description = (string) $ability->description;
			}
			if ( method_exists( $ability, 'get_meta' ) ) {
				$meta = $ability->get_meta();
			} elseif ( isset( $ability->meta ) ) {
				$meta = $ability->meta;
			}
		} elseif ( is_array( $ability ) ) {
			$name        = isset( $ability['name'] ) ? (string) $ability['name'] : '';
			$label       = isset( $ability['label'] ) ? (string) $ability['label'] : '';
			$description = isset( $ability['description'] ) ? (string) $ability['description'] : '';
			$meta        = isset( $ability['meta'] ) && is_array( $ability['meta'] ) ? $ability['meta'] : array();
		}

		if ( '' === $name ) {
			return null;
		}

		$annotations = $this->extract_annotations( $meta );
		$inferred    = false;
		if ( empty( $annotations ) ) {
			$annotations = $this->infer_annotations( $name );
			$inferred    = ! empty( $annotations );
		}
		$managed = ( 0 === strpos( $name, self::MANAGED_NS ) );

		return array(
			'name'        => $name,
			'label'       => $label,
			'description' => $description,
			'source'      => $this->get_source_plugin( $name ),
			'annotations' => $annotations,
			'inferred'    => $inferred,
			'managed'     => $managed,
		);
	}

	/**
	 * Heuristically infer annotations from an ability's name when the
	 * registering plugin didn't declare any.
	 *
	 * Conservative: matches common verb tokens in the slug. Inferred
	 * annotations are rendered with a distinct visual treatment so the
	 * user can tell them apart from declared annotations.
	 *
	 * @param string $name Ability name (e.g. `respira/wordpress-update-page`).
	 * @return array<int, string>
	 */
	private function infer_annotations( $name ) {
		$slug = strtolower( $name );
		$pos  = strpos( $slug, '/' );
		if ( false !== $pos ) {
			$slug = substr( $slug, $pos + 1 );
		}
		$tokens = preg_split( '/[-_\s]+/', $slug );
		if ( ! is_array( $tokens ) || empty( $tokens ) ) {
			return array();
		}

		$read_tokens = array(
			'get', 'list', 'read', 'show', 'fetch', 'search', 'find', 'view', 'count',
			'info', 'validate', 'check', 'analyze', 'analyse', 'scan', 'diagnose',
			'preview', 'inspect', 'describe', 'lookup', 'has',
		);
		$write_tokens = array(
			'create', 'add', 'insert', 'new', 'register', 'install',
			'update', 'set', 'edit', 'modify', 'patch', 'change', 'rename',
			'move', 'duplicate', 'clone', 'copy', 'merge', 'apply',
			'delete', 'remove', 'destroy', 'purge', 'drop', 'flush', 'trash', 'erase',
			'uninstall', 'activate', 'deactivate', 'enable', 'disable',
			'switch', 'send', 'submit', 'publish', 'unpublish', 'approve', 'reject',
			'upload', 'import', 'sync', 'restore', 'rollback', 'reset', 'sideload',
			'redeem', 'assign', 'bulk', 'batch',
		);
		$idempotent_tokens = array(
			'update', 'set', 'restore', 'rollback', 'apply', 'sync', 'install',
			'activate', 'deactivate', 'enable', 'disable',
		);

		$flags = array();
		foreach ( $tokens as $tok ) {
			if ( in_array( $tok, $read_tokens, true ) ) {
				$flags['read-only'] = true;
			}
			if ( in_array( $tok, $write_tokens, true ) ) {
				$flags['destructive'] = true;
			}
			if ( in_array( $tok, $idempotent_tokens, true ) ) {
				$flags['idempotent'] = true;
			}
		}

		// If both read and write tokens matched (e.g. "get-and-update-..."),
		// the destructive signal wins (safer side of the doubt).
		if ( isset( $flags['destructive'] ) ) {
			unset( $flags['read-only'] );
		}

		return array_keys( $flags );
	}

	/**
	 * Extract annotation flags from ability meta. Tries a few shapes
	 * because the Abilities API doesn't pin one down.
	 *
	 * Returns a flat list of strings drawn from this whitelist:
	 *  - read-only
	 *  - destructive
	 *  - idempotent
	 *
	 * @param mixed $meta Ability meta.
	 * @return array<int, string>
	 */
	private function extract_annotations( $meta ) {
		if ( ! is_array( $meta ) ) {
			return array();
		}

		$flags = array();

		if ( ! empty( $meta['readonly'] ) || ! empty( $meta['read_only'] ) ) {
			$flags[] = 'read-only';
		}

		$annot_block = null;
		if ( isset( $meta['annotations'] ) && is_array( $meta['annotations'] ) ) {
			$annot_block = $meta['annotations'];
		}

		if ( is_array( $annot_block ) ) {
			if ( isset( $annot_block['readOnlyHint'] ) && $annot_block['readOnlyHint'] ) {
				$flags[] = 'read-only';
			}
			if ( isset( $annot_block['destructiveHint'] ) && $annot_block['destructiveHint'] ) {
				$flags[] = 'destructive';
			}
			if ( isset( $annot_block['idempotentHint'] ) && $annot_block['idempotentHint'] ) {
				$flags[] = 'idempotent';
			}
			if ( ! empty( $annot_block['read_only'] ) || ! empty( $annot_block['readonly'] ) ) {
				$flags[] = 'read-only';
			}
			if ( ! empty( $annot_block['destructive'] ) ) {
				$flags[] = 'destructive';
			}
			if ( ! empty( $annot_block['idempotent'] ) ) {
				$flags[] = 'idempotent';
			}
		}

		$flags = array_values( array_unique( $flags ) );
		return $flags;
	}

	/**
	 * Resolve the source plugin or theme for an ability.
	 *
	 * Best-effort: maps the namespace prefix to a known plugin or theme.
	 * Falls back to a human-readable rendering of the namespace.
	 *
	 * @param string $ability_name e.g. "core/get-posts".
	 * @return string
	 */
	public function get_source_plugin( $ability_name ) {
		if ( '' === $ability_name ) {
			return __( 'Unknown source', 'inhale-mcp-abilities' );
		}

		$pos = strpos( $ability_name, '/' );
		if ( false === $pos ) {
			return $ability_name;
		}

		$ns = substr( $ability_name, 0, $pos );

		$labels = $this->namespace_label_map();
		if ( isset( $labels[ $ns ] ) ) {
			return $labels[ $ns ];
		}

		return $ns;
	}

	/**
	 * Map of namespaces to human-readable source labels.
	 *
	 * @return array<string, string>
	 */
	private function namespace_label_map() {
		$map = array(
			'core'                 => __( 'WordPress core', 'inhale-mcp-abilities' ),
			'wp'                   => __( 'WordPress core', 'inhale-mcp-abilities' ),
			'mcp-adapter'          => __( 'MCP Adapter (managed)', 'inhale-mcp-abilities' ),
			'respira'              => __( 'Respira for WordPress', 'inhale-mcp-abilities' ),
			'respira-woocommerce'  => __( 'Respira WooCommerce', 'inhale-mcp-abilities' ),
			'ai-engine'            => __( 'AI Engine', 'inhale-mcp-abilities' ),
			'wpforms'              => __( 'WPForms', 'inhale-mcp-abilities' ),
			'yoast'                => __( 'Yoast SEO', 'inhale-mcp-abilities' ),
		);

		/**
		 * Filter the namespace-to-label map for the source column.
		 *
		 * @param array<string, string> $map Default map.
		 */
		return apply_filters( 'respira_inhale_source_labels', $map );
	}

	/**
	 * Build the counts shown in the subsubsub filter row.
	 *
	 * @param array<int, array<string, mixed>> $abilities Normalised ability rows.
	 * @param array<int, string>               $exposed   Saved option.
	 * @return array<string, int>
	 */
	private function build_counts( $abilities, $exposed ) {
		$counts = array(
			'all'         => 0,
			'inhaled'     => 0,
			'read-only'   => 0,
			'destructive' => 0,
			'unannotated' => 0,
		);

		foreach ( $abilities as $a ) {
			++$counts['all'];

			$is_inhaled = in_array( $a['name'], $exposed, true ) || ! empty( $a['managed'] );
			if ( $is_inhaled ) {
				++$counts['inhaled'];
			}

			if ( in_array( 'read-only', $a['annotations'], true ) ) {
				++$counts['read-only'];
			}
			if ( in_array( 'destructive', $a['annotations'], true ) ) {
				++$counts['destructive'];
			}
			if ( empty( $a['annotations'] ) ) {
				++$counts['unannotated'];
			}
		}

		return $counts;
	}

	/**
	 * Render the settings page.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die(
				esc_html__( 'You do not have permission to access this page.', 'inhale-mcp-abilities' ),
				esc_html__( 'Permission denied', 'inhale-mcp-abilities' ),
				array(
					'response'  => 403,
					'back_link' => true,
				)
			);
		}

		$this->maybe_process_bulk_action();

		$abilities       = $this->discover_abilities();
		$exposed         = $this->get_exposed();
		$counts          = $this->build_counts( $abilities, $exposed );
		$sources         = $this->build_source_list( $abilities );
		$source_summary  = $this->build_source_summary( $abilities );
		// Build the endpoint with rest_url() so the REST base (custom prefixes,
		// non-default permalink structures, multisite blogs, sub-directory
		// installs, etc.) is resolved by WordPress core rather than hardcoded.
		$endpoint        = esc_url( rest_url( self::DEFAULT_SERVER_REST_PATH ) );

		?>
		<div class="wrap inhale-wrap" data-theme="light">
			<?php $this->render_notice(); ?>
			<?php $this->render_review_request(); ?>

			<div class="page-head">
				<div class="page-head-text">
					<h1 class="inhale-h1">
						<span class="inhale-mark" aria-hidden="true">
							<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
								<g class="inhale-mark-grid">
									<circle cx="20" cy="20" r="4"/><circle cx="32" cy="20" r="4"/><circle cx="44" cy="20" r="4"/><circle cx="56" cy="20" r="4"/><circle cx="68" cy="20" r="4"/><circle cx="80" cy="20" r="4"/>
									<circle cx="20" cy="32" r="4"/><circle cx="32" cy="32" r="4"/><circle cx="44" cy="32" r="4"/><circle cx="56" cy="32" r="4"/><circle cx="68" cy="32" r="4"/><circle cx="80" cy="32" r="4"/>
									<circle cx="20" cy="44" r="4"/><circle cx="32" cy="44" r="4"/><circle cx="44" cy="44" r="4"/><circle cx="56" cy="44" r="4"/><circle cx="68" cy="44" r="4"/><circle cx="80" cy="44" r="4"/>
									<circle cx="20" cy="56" r="4"/><circle cx="32" cy="56" r="4"/><circle cx="44" cy="56" r="4"/><circle cx="56" cy="56" r="4"/><circle cx="68" cy="56" r="4"/><circle cx="80" cy="56" r="4"/>
									<circle cx="20" cy="68" r="4"/><circle cx="32" cy="68" r="4"/><circle cx="44" cy="68" r="4"/><circle cx="56" cy="68" r="4"/><circle cx="68" cy="68" r="4"/><circle cx="80" cy="68" r="4"/>
									<circle cx="20" cy="80" r="4"/><circle cx="32" cy="80" r="4"/><circle cx="44" cy="80" r="4"/><circle cx="56" cy="80" r="4"/><circle cx="68" cy="80" r="4"/><circle cx="80" cy="80" r="4"/>
								</g>
								<g class="inhale-mark-accent">
									<circle cx="32" cy="32" r="4"/><circle cx="44" cy="56" r="4"/><circle cx="56" cy="20" r="4"/><circle cx="68" cy="68" r="4"/><circle cx="20" cy="80" r="4"/>
								</g>
							</svg>
						</span>
						<span class="inhale-h1-titles">
							<span class="inhale-h1-text"><?php esc_html_e( 'Inhale: MCP Abilities', 'inhale-mcp-abilities' ); ?></span>
							<span class="inhale-h1-by">
								<?php esc_html_e( 'by', 'inhale-mcp-abilities' ); ?>
								<a href="https://respira.press/?utm_source=inhale&amp;utm_medium=wp-admin&amp;utm_campaign=settings-header" target="_blank" rel="noopener noreferrer">respira.press</a>
							</span>
						</span>
					</h1>
					<p class="page-desc"><?php esc_html_e( 'Decide which registered abilities are visible to the default MCP server.', 'inhale-mcp-abilities' ); ?></p>
					<span class="accent-line" aria-hidden="true"></span>
				</div>
				<div class="page-head-tools">
					<span class="inhale-h1-version" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin version. */ __( 'Plugin version %s', 'inhale-mcp-abilities' ), RESPIRA_INHALE_VERSION ) ); ?>">v<?php echo esc_html( RESPIRA_INHALE_VERSION ); ?></span>
					<a class="docs-link"
						href="https://respira.press/inhale?utm_source=inhale&amp;utm_medium=wp-admin&amp;utm_campaign=settings-docs"
						target="_blank"
						rel="noopener noreferrer">
						<?php esc_html_e( 'Documentation', 'inhale-mcp-abilities' ); ?>
						<svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M4 2H2v7h7V7"/>
							<path d="M6 2h3v3"/>
							<path d="M9 2L5 6"/>
						</svg>
					</a>
					<a class="docs-link"
						href="https://www.respira.press/abilities?utm_source=inhale&amp;utm_medium=wp-admin&amp;utm_campaign=settings-abilities-directory"
						target="_blank"
						rel="noopener noreferrer">
						<?php esc_html_e( 'Abilities directory', 'inhale-mcp-abilities' ); ?>
						<svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M4 2H2v7h7V7"/>
							<path d="M6 2h3v3"/>
							<path d="M9 2L5 6"/>
						</svg>
					</a>
					<button type="button"
						class="theme-toggle"
						id="inhaleThemeToggle"
						aria-label="<?php esc_attr_e( 'Toggle light or dark mode for the Inhale: MCP Abilities plugin', 'inhale-mcp-abilities' ); ?>"
						data-tooltip="<?php esc_attr_e( 'Toggle dark mode', 'inhale-mcp-abilities' ); ?>">
						<svg class="icon-sun" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true">
							<circle cx="10" cy="10" r="3.5"/>
							<path d="M10 2v2M10 16v2M2 10h2M16 10h2M4.2 4.2l1.4 1.4M14.4 14.4l1.4 1.4M4.2 15.8l1.4-1.4M14.4 5.6l1.4-1.4"/>
						</svg>
						<svg class="icon-moon" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M16.5 12.4A7 7 0 017.6 3.5a7 7 0 108.9 8.9z"/>
						</svg>
					</button>
				</div>
			</div>

			<?php $this->render_status_card( $abilities, $exposed, $endpoint ); ?>

			<?php if ( class_exists( 'Respira_Inhale_Free_Connect' ) ) : ?>
				<div class="inhale-free-connect"><?php Respira_Inhale_Free_Connect::render_card(); ?></div>
			<?php endif; ?>

			<?php if ( ! empty( $source_summary ) ) : ?>
				<aside class="inhale-sources-card" aria-labelledby="inhale-sources-h">
					<h2 id="inhale-sources-h" class="inhale-sources-card__title">
						<?php
						/* translators: %d: total number of source plugins/themes registering abilities. */
						echo esc_html( sprintf( _n( '%d source', '%d sources', count( $source_summary ), 'inhale-mcp-abilities' ), count( $source_summary ) ) );
						?>
					</h2>
					<ul class="inhale-sources-card__list">
						<?php foreach ( $source_summary as $row ) : ?>
							<li class="inhale-sources-card__row">
								<?php if ( '' !== $row['url'] ) : ?>
									<a class="inhale-sources-card__link" href="<?php echo esc_url( $row['url'] ); ?>">
										<span class="inhale-sources-card__label"><?php echo esc_html( $row['label'] ); ?></span>
										<span class="inhale-sources-card__count"><?php echo (int) $row['count']; ?></span>
									</a>
								<?php else : ?>
									<span class="inhale-sources-card__link inhale-sources-card__link--static">
										<span class="inhale-sources-card__label"><?php echo esc_html( $row['label'] ); ?></span>
										<span class="inhale-sources-card__count"><?php echo (int) $row['count']; ?></span>
									</span>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ul>
				</aside>
			<?php endif; ?>

			<?php if ( class_exists( 'WooCommerce' ) ) : ?>
				<?php if ( defined( 'RESPIRA_ARC_VERSION' ) || defined( 'RESPIRA_WOO_VERSION' ) ) : ?>
					<aside class="inhale-sources-card" aria-labelledby="inhale-arc-h">
						<h2 id="inhale-arc-h" class="inhale-sources-card__title"><?php esc_html_e( 'Respira ARC is active', 'inhale-mcp-abilities' ); ?></h2>
						<p style="margin:8px 0 0; font-size:13px; line-height:1.5;"><?php esc_html_e( 'This store is already readable to AI shopping assistants: feeds, llms.txt, readiness score and cart links are handled under WooCommerce, Respira ARC.', 'inhale-mcp-abilities' ); ?></p>
					</aside>
				<?php else : ?>
					<aside class="inhale-sources-card" aria-labelledby="inhale-arc-h">
						<h2 id="inhale-arc-h" class="inhale-sources-card__title"><?php esc_html_e( 'Free for this store: Respira ARC', 'inhale-mcp-abilities' ); ?></h2>
						<p style="margin:8px 0 10px; font-size:13px; line-height:1.5;"><?php esc_html_e( 'Inhale exposes your plugins\' abilities to MCP. Respira ARC, the free companion for WooCommerce, makes the store itself readable to AI shopping assistants: product feeds in six formats, a store llms.txt, an AI-readiness score, and attributed cart links. No accounts, no product caps, runs entirely on your server.', 'inhale-mcp-abilities' ); ?></p>
						<a href="https://respira.press/arc?utm_source=inhale&amp;utm_medium=wp-admin&amp;utm_campaign=arc-promo" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get Respira ARC free', 'inhale-mcp-abilities' ); ?> &rarr;</a>
					</aside>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="" id="inhaleAbilitiesForm">
				<?php wp_nonce_field( self::NONCE_ACTION ); ?>
				<input type="hidden" name="page" value="<?php echo esc_attr( self::MENU_SLUG ); ?>" />

				<div class="filters-row">
					<div class="filters-row-left">
						<ul class="subsubsub" role="navigation" aria-label="<?php esc_attr_e( 'Filter abilities', 'inhale-mcp-abilities' ); ?>">
							<li><a href="#" class="current" aria-current="page" data-view="all"><?php esc_html_e( 'All', 'inhale-mcp-abilities' ); ?> <span class="count">(<?php echo (int) $counts['all']; ?>)</span></a></li>
							<li><a href="#" data-view="inhaled"><?php esc_html_e( 'Inhaled', 'inhale-mcp-abilities' ); ?> <span class="count">(<?php echo (int) $counts['inhaled']; ?>)</span></a></li>
							<li><a href="#" data-view="read-only"><?php esc_html_e( 'Read-only', 'inhale-mcp-abilities' ); ?> <span class="count">(<?php echo (int) $counts['read-only']; ?>)</span></a></li>
							<li><a href="#" data-view="destructive"><?php esc_html_e( 'Destructive', 'inhale-mcp-abilities' ); ?> <span class="count">(<?php echo (int) $counts['destructive']; ?>)</span></a></li>
							<li><a href="#" data-view="unannotated"><?php esc_html_e( 'Unannotated', 'inhale-mcp-abilities' ); ?> <span class="count">(<?php echo (int) $counts['unannotated']; ?>)</span></a></li>
						</ul>
						<button type="button" class="reset-filters" id="inhaleResetFilters"><?php esc_html_e( 'Reset filters', 'inhale-mcp-abilities' ); ?></button>
					</div>
					<div class="search-box">
						<label for="inhale-ability-search" class="screen-reader-text"><?php esc_html_e( 'Search abilities, sources, and descriptions', 'inhale-mcp-abilities' ); ?></label>
						<input type="search"
							id="inhale-ability-search"
							placeholder="<?php esc_attr_e( 'Search abilities', 'inhale-mcp-abilities' ); ?>" />
					</div>
				</div>

				<?php $this->render_tablenav( 'top', $counts ); ?>

				<table class="wp-list-table widefat fixed inhale-table" role="grid" id="inhaleAbilitiesTable">
					<thead>
						<tr>
							<th scope="col" class="manage-column column-cb col-check">
								<label for="inhale-cb-select-all-top" class="screen-reader-text"><?php esc_html_e( 'Select all abilities', 'inhale-mcp-abilities' ); ?></label>
								<input type="checkbox" id="inhale-cb-select-all-top" class="inhale-select-all" />
							</th>
							<th scope="col" class="manage-column col-ability sortable" data-sort="ability" aria-sort="none">
								<?php esc_html_e( 'Ability', 'inhale-mcp-abilities' ); ?>
								<span class="sort-glyph" aria-hidden="true"><svg viewBox="0 0 9 11" fill="currentColor"><path d="M4.5 0L9 4H0z" opacity="0.55"/><path d="M4.5 11L0 7h9z" opacity="0.55"/></svg></span>
							</th>
							<th scope="col" class="manage-column col-source sortable" data-sort="source" aria-sort="none">
								<?php esc_html_e( 'Source', 'inhale-mcp-abilities' ); ?>
								<span class="sort-glyph" aria-hidden="true"><svg viewBox="0 0 9 11" fill="currentColor"><path d="M4.5 0L9 4H0z" opacity="0.55"/><path d="M4.5 11L0 7h9z" opacity="0.55"/></svg></span>
								<span class="col-filter">
									<button type="button"
										class="filter-btn"
										id="inhaleSourceFilterBtn"
										aria-haspopup="true"
										aria-expanded="false"
										aria-label="<?php esc_attr_e( 'Filter by source plugin', 'inhale-mcp-abilities' ); ?>"
										title="<?php esc_attr_e( 'Filter by source', 'inhale-mcp-abilities' ); ?>">
										<svg viewBox="0 0 9 9" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1.5 3l3 3 3-3"/></svg>
										<span class="filter-count" id="inhaleSourceFilterCount" style="display:none;">0</span>
									</button>
									<div class="filter-popover" id="inhaleSourceFilterPop" role="dialog" aria-label="<?php esc_attr_e( 'Filter abilities by source plugin', 'inhale-mcp-abilities' ); ?>">
										<div class="pop-head"><?php esc_html_e( 'Filter by source', 'inhale-mcp-abilities' ); ?></div>
										<?php foreach ( $sources as $source_label ) : ?>
											<label><input type="checkbox" value="<?php echo esc_attr( $source_label ); ?>" /> <?php echo esc_html( $source_label ); ?></label>
										<?php endforeach; ?>
										<?php if ( empty( $sources ) ) : ?>
											<label style="color:var(--fg-dim);font-style:italic;cursor:default;"><?php esc_html_e( 'No sources to filter', 'inhale-mcp-abilities' ); ?></label>
										<?php endif; ?>
										<div class="pop-foot">
											<button type="button" class="pop-clear" id="inhaleSourceFilterClear"><?php esc_html_e( 'Clear', 'inhale-mcp-abilities' ); ?></button>
										</div>
									</div>
								</span>
							</th>
							<th scope="col" class="manage-column col-desc sortable" data-sort="desc" aria-sort="none">
								<?php esc_html_e( 'Description', 'inhale-mcp-abilities' ); ?>
								<span class="sort-glyph" aria-hidden="true"><svg viewBox="0 0 9 11" fill="currentColor"><path d="M4.5 0L9 4H0z" opacity="0.55"/><path d="M4.5 11L0 7h9z" opacity="0.55"/></svg></span>
							</th>
							<th scope="col" class="manage-column col-status sortable" data-sort="status" aria-sort="none">
								<?php esc_html_e( 'Status', 'inhale-mcp-abilities' ); ?>
								<span class="sort-glyph" aria-hidden="true"><svg viewBox="0 0 9 11" fill="currentColor"><path d="M4.5 0L9 4H0z" opacity="0.55"/><path d="M4.5 11L0 7h9z" opacity="0.55"/></svg></span>
							</th>
							<th scope="col" class="manage-column col-annot"><?php esc_html_e( 'Annotations', 'inhale-mcp-abilities' ); ?></th>
						</tr>
					</thead>
					<tbody id="inhaleAbilitiesBody">
						<?php if ( empty( $abilities ) ) : ?>
							<tr class="empty-state">
								<td colspan="6">
									<?php esc_html_e( 'No abilities are currently registered on this site. Activate the WordPress MCP Adapter and any plugins that register abilities to see them here.', 'inhale-mcp-abilities' ); ?>
								</td>
							</tr>
						<?php else : ?>
							<?php foreach ( $abilities as $a ) : ?>
								<?php $this->render_row( $a, in_array( $a['name'], $exposed, true ) ); ?>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					<tfoot>
						<tr>
							<th scope="col" class="manage-column column-cb col-check">
								<label for="inhale-cb-select-all-bottom" class="screen-reader-text"><?php esc_html_e( 'Select all abilities', 'inhale-mcp-abilities' ); ?></label>
								<input type="checkbox" id="inhale-cb-select-all-bottom" class="inhale-select-all" />
							</th>
							<th scope="col" class="manage-column col-ability"><?php esc_html_e( 'Ability', 'inhale-mcp-abilities' ); ?></th>
							<th scope="col" class="manage-column col-source"><?php esc_html_e( 'Source', 'inhale-mcp-abilities' ); ?></th>
							<th scope="col" class="manage-column col-desc"><?php esc_html_e( 'Description', 'inhale-mcp-abilities' ); ?></th>
							<th scope="col" class="manage-column col-status"><?php esc_html_e( 'Status', 'inhale-mcp-abilities' ); ?></th>
							<th scope="col" class="manage-column col-annot"><?php esc_html_e( 'Annotations', 'inhale-mcp-abilities' ); ?></th>
						</tr>
					</tfoot>
				</table>

				<?php $this->render_tablenav( 'bottom', $counts ); ?>
			</form>

			<hr class="divider"/>

			<section class="section inhale-legend" aria-labelledby="inhale-legend-h">
				<h2 id="inhale-legend-h"><?php esc_html_e( 'What the annotations mean', 'inhale-mcp-abilities' ); ?></h2>
				<p><?php esc_html_e( 'Each ability can carry metadata that tells you, and your MCP client, how safe it is to call. The annotations come from the plugin that registered the ability. Where the plugin did not declare any, Inhale: MCP Abilities infers them from the ability name and marks them with an asterisk.', 'inhale-mcp-abilities' ); ?></p>
				<dl class="inhale-legend__grid">
					<div class="inhale-legend__row">
						<dt><span class="annot neutral"><?php esc_html_e( 'read-only', 'inhale-mcp-abilities' ); ?></span></dt>
						<dd><?php esc_html_e( 'The ability only reads data. It cannot create, modify or delete anything on your site. Safe to call repeatedly. Examples: list products, get a page, retrieve users.', 'inhale-mcp-abilities' ); ?></dd>
					</div>
					<div class="inhale-legend__row">
						<dt><span class="annot destructive"><svg class="glyph" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true"><path d="M5 1.4L9 8.6H1z"/></svg><?php esc_html_e( 'destructive', 'inhale-mcp-abilities' ); ?></span></dt>
						<dd><?php esc_html_e( 'The ability can create, modify or delete content. Inhaling a destructive ability requires explicit confirmation. Examples: create a product, update an order, delete a category.', 'inhale-mcp-abilities' ); ?></dd>
					</div>
					<div class="inhale-legend__row">
						<dt><span class="annot neutral"><?php esc_html_e( 'idempotent', 'inhale-mcp-abilities' ); ?></span></dt>
						<dd><?php esc_html_e( 'Running the ability multiple times has the same effect as running it once. Often paired with destructive. Useful signal for AI agents that may retry. Examples: set a setting to a value, ensure a category exists.', 'inhale-mcp-abilities' ); ?></dd>
					</div>
					<div class="inhale-legend__row">
						<dt><span class="annot neutral inferred"><?php esc_html_e( 'read-only', 'inhale-mcp-abilities' ); ?><span class="annot-inferred-mark" aria-hidden="true">*</span></span></dt>
						<dd><?php esc_html_e( 'Inferred annotation. The registering plugin did not declare it. Inhale: MCP Abilities guesses from the ability name (verbs like get, list, read). Treat inferred read-only as conservative; verify before granting an MCP client write access to that ability.', 'inhale-mcp-abilities' ); ?></dd>
					</div>
					<div class="inhale-legend__row">
						<dt><span class="annot-none"><?php esc_html_e( 'no annotations', 'inhale-mcp-abilities' ); ?></span></dt>
						<dd><?php esc_html_e( 'The registering plugin did not declare any safety hints, and the name did not match a known verb. Treat it as unknown. Check the ability description and the source plugin before inhaling.', 'inhale-mcp-abilities' ); ?></dd>
					</div>
				</dl>
			</section>

			<hr class="divider"/>

			<section class="section" aria-labelledby="inhale-connection-h">
				<h2 id="inhale-connection-h"><?php esc_html_e( 'Connection', 'inhale-mcp-abilities' ); ?></h2>
				<p><?php esc_html_e( 'Your default MCP server endpoint:', 'inhale-mcp-abilities' ); ?></p>
				<div class="code-block">
					<code id="inhaleEndpoint"><?php echo esc_html( $endpoint ); ?></code>
					<button type="button" class="copy-btn" id="inhaleCopyEndpoint" aria-label="<?php esc_attr_e( 'Copy endpoint', 'inhale-mcp-abilities' ); ?>">
						<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" aria-hidden="true"><rect x="4" y="4" width="8" height="8" rx="1"/><path d="M2 10V3a1 1 0 011-1h7"/></svg>
						<span class="copy-btn-label"><?php esc_html_e( 'Copy', 'inhale-mcp-abilities' ); ?></span>
					</button>
				</div>

				<details class="disclosure">
					<summary><?php esc_html_e( 'Connect with Respira for WordPress (easiest)', 'inhale-mcp-abilities' ); ?></summary>
					<div class="disclosure-body">
						<p style="margin:0 0 6px;"><?php esc_html_e( 'Respira for WordPress connects this site to Claude, ChatGPT, Cursor or Codex in two clicks from the respira.press dashboard: no application passwords, no config files, no terminal. Its native connector carries these abilities alongside its own tools.', 'inhale-mcp-abilities' ); ?></p>
						<ul style="margin:0 0 8px; padding-left:18px; list-style:disc;">
							<li><?php esc_html_e( 'Duplicate-before-edit safety: the AI edits a copy, you approve, snapshots keep 90 days of rollback', 'inhale-mcp-abilities' ); ?></li>
							<li><?php esc_html_e( 'Element-level editing in the page builder each site already uses, not just raw content', 'inhale-mcp-abilities' ); ?></li>
							<li><?php esc_html_e( 'Signs in from claude.ai and ChatGPT in the browser too, with no Application Password', 'inhale-mcp-abilities' ); ?></li>
							<li><?php esc_html_e( '7-day free trial, no card', 'inhale-mcp-abilities' ); ?></li>
						</ul>
						<p style="margin:0;"><a href="https://respira.press/?utm_source=inhale&amp;utm_medium=wp-admin&amp;utm_campaign=connection-respira" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Connect with Respira', 'inhale-mcp-abilities' ); ?> &rarr;</a></p>
					</div>
				</details>

				<details class="disclosure">
					<summary><?php esc_html_e( 'Connect with WP-CLI (STDIO)', 'inhale-mcp-abilities' ); ?></summary>
					<div class="disclosure-body">
						<p style="margin:0 0 6px;"><?php esc_html_e( 'STDIO transport requires WordPress and your MCP client to run on the same machine. For remote sites use the HTTP transport snippet below.', 'inhale-mcp-abilities' ); ?></p>
						<p style="margin:0 0 6px;"><?php
							echo wp_kses(
								/* translators: %s: the filename `claude_desktop_config.json` wrapped in <code> tags. */
								sprintf( __( 'Paste this into your Claude Desktop config (%s):', 'inhale-mcp-abilities' ), '<code>claude_desktop_config.json</code>' ),
								array( 'code' => array() )
							);
						?></p>
<pre>{
  "mcpServers": {
    "wordpress-inhale": {
      "command": "wp",
      "args": [
        "--path=<?php echo esc_html( ABSPATH ); ?>",
        "mcp-adapter",
        "serve",
        "--user=admin",
        "--server=mcp-adapter-default-server"
      ]
    }
  }
}</pre>
					</div>
				</details>

				<details class="disclosure">
					<summary><?php esc_html_e( 'Connect with an Application Password (Claude Desktop, Claude Code, Cursor, VS Code)', 'inhale-mcp-abilities' ); ?></summary>
					<div class="disclosure-body">
						<ol>
							<li><?php
								echo wp_kses(
									__( 'Go to <em>Users &gt; Profile &gt; Application Passwords</em>, create one named after the client (for example "Claude, laptop") and copy it. WordPress shows it only once; the spaces are part of it.', 'inhale-mcp-abilities' ),
									array( 'em' => array() )
								);
							?></li>
							<li><?php esc_html_e( 'Desktop apps that read a JSON config (Claude Desktop, Cursor, VS Code) run a small helper with npx, so Node.js must be installed. Add this entry and restart the app:', 'inhale-mcp-abilities' ); ?></li>
						</ol>
<pre>{
  "mcpServers": {
    "my-wordpress-site": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "<?php echo esc_html( $endpoint ); ?>",
        "WP_API_USERNAME": "your-username",
        "WP_API_PASSWORD": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}</pre>
						<p><?php esc_html_e( 'Clients that accept a URL and a header, such as Claude Code, connect directly. Encode your username and Application Password as one line, then add the server:', 'inhale-mcp-abilities' ); ?></p>
<pre>printf 'your-username:xxxx xxxx xxxx xxxx xxxx xxxx' | base64 | tr -d '\n'

claude mcp add --transport http my-wordpress-site \
  <?php echo esc_html( $endpoint ); ?> \
  --header "Authorization: Basic PASTE_THE_BASE64_HERE"</pre>
						<p class="muted"><?php esc_html_e( 'claude.ai and ChatGPT in the browser sign in with OAuth and have nowhere to put an Application Password, so they cannot use this endpoint on its own.', 'inhale-mcp-abilities' ); ?></p>
					</div>
				</details>
			</section>

			<hr class="divider"/>

			<section class="section" aria-labelledby="inhale-about-h">
				<h2 id="inhale-about-h"><?php esc_html_e( 'About Inhale: MCP Abilities', 'inhale-mcp-abilities' ); ?></h2>
				<p><?php esc_html_e( 'Inhale: MCP Abilities is a settings-only utility. It does not run MCP servers, transports, or authentication. Those come from the WordPress MCP Adapter, whichever plugin loads it: the MCP Adapter plugin itself, or a copy shipped inside another plugin.', 'inhale-mcp-abilities' ); ?></p>
				<p><?php esc_html_e( 'Every ability you inhale still runs its own permission checks before execution. The Inhale: MCP Abilities plugin controls visibility, not authorization.', 'inhale-mcp-abilities' ); ?></p>
				<p class="muted"><?php esc_html_e( 'Model Context Protocol (MCP) is an open specification originally developed by Anthropic. Inhale: MCP Abilities is a third-party plugin and is not affiliated with, endorsed by, or sponsored by Anthropic. Respira is an independent company.', 'inhale-mcp-abilities' ); ?></p>
			</section>

			<hr class="divider"/>

			<p class="muted inhale-respira-footer"><?php
				echo wp_kses(
					sprintf(
						/* translators: 1: link to Respira for WordPress, 2: link to respira.press, 3: link to the abilities directory. */
						__( 'The Inhale: MCP Abilities plugin is built by Respira, which ships AI infrastructure for WordPress. The main product is %1$s, which lets AI apps edit WordPress sites in their own page builder (Elementor, Bricks, Divi, Beaver Builder, Oxygen, Breakdance and more), with a snapshot before every write and one-click rollback. Learn more at %2$s, or browse the public abilities directory at %3$s.', 'inhale-mcp-abilities' ),
						'<a href="https://respira.press/?utm_source=inhale&utm_medium=wp-admin&utm_campaign=settings-footer-product" target="_blank" rel="noopener noreferrer">Respira for WordPress</a>',
						'<a href="https://respira.press/?utm_source=inhale&utm_medium=wp-admin&utm_campaign=settings-footer-cta" target="_blank" rel="noopener noreferrer">respira.press</a>',
						'<a href="https://www.respira.press/abilities?utm_source=inhale&utm_medium=wp-admin&utm_campaign=settings-footer-abilities-directory" target="_blank" rel="noopener noreferrer">respira.press/abilities</a>'
					),
					array(
						'a' => array(
							'href'   => true,
							'target' => true,
							'rel'    => true,
						),
					)
				);
			?></p>
		</div>
		<?php
	}

	/**
	 * Which MCP Adapter runs here, where it comes from, and whether a client can sign in.
	 *
	 * Many sites get the adapter from another plugin (SEO, page builder, form
	 * and store plugins ship their own copy), so "is it running" and "who
	 * provides it" are the first two questions when a client connects and
	 * finds nothing. Read-only: nothing here changes a setting.
	 *
	 * @since 0.6.0
	 *
	 * @return array{running: bool, version: string, provider: string}
	 */
	public static function adapter_status() {
		$status = array(
			'running'  => false,
			'version'  => '',
			'provider' => '',
		);
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return $status;
		}
		$status['running'] = true;
		if ( defined( 'WP\MCP\Core\McpAdapter::VERSION' ) ) {
			$status['version'] = (string) constant( 'WP\MCP\Core\McpAdapter::VERSION' );
		}
		try {
			$file = wp_normalize_path( (string) ( new ReflectionClass( '\WP\MCP\Core\McpAdapter' ) )->getFileName() );
			$base = trailingslashit( wp_normalize_path( WP_PLUGIN_DIR ) );
			if ( 0 === strpos( $file, $base ) ) {
				$folder = strtok( substr( $file, strlen( $base ) ), '/' );
				$status['provider'] = (string) $folder;
				if ( ! function_exists( 'get_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				foreach ( get_plugins() as $plugin_file => $data ) {
					if ( 0 === strpos( $plugin_file, $folder . '/' ) && ! empty( $data['Name'] ) ) {
						$status['provider'] = $data['Name'];
						break;
					}
				}
			}
		} catch ( \Throwable $e ) {
			$status['provider'] = '';
		}
		return $status;
	}

	/**
	 * The status card at the top of the page.
	 *
	 * @since 0.6.0
	 *
	 * @param array  $abilities Abilities from discover_abilities().
	 * @param array  $exposed   Names the admin has inhaled.
	 * @param string $endpoint  Default server endpoint, already escaped.
	 */
	private function render_status_card( $abilities, $exposed, $endpoint ) {
		// The adapter's own discovery tools (managed) are not the site's abilities.
		$own_total   = 0;
		$own_exposed = 0;
		foreach ( $abilities as $a ) {
			if ( ! empty( $a['managed'] ) ) {
				continue;
			}
			++$own_total;
			if ( in_array( $a['name'], $exposed, true ) ) {
				++$own_exposed;
			}
		}
		$adapter = self::adapter_status();
		$app_pw  = function_exists( 'wp_is_application_passwords_available' ) && wp_is_application_passwords_available();
		$https   = function_exists( 'wp_is_application_passwords_supported' ) ? wp_is_application_passwords_supported() : is_ssl();
		$install = current_user_can( 'install_plugins' )
			? admin_url( 'plugin-install.php?s=mcp-adapter&tab=search&type=term' )
			: 'https://wordpress.org/plugins/mcp-adapter/';
		?>
		<section class="inhale-sources-card inhale-status-card" aria-labelledby="inhale-status-h">
			<h2 id="inhale-status-h" class="inhale-sources-card__title"><?php esc_html_e( 'Your MCP setup', 'inhale-mcp-abilities' ); ?></h2>
			<dl class="inhale-status">
				<div class="inhale-status__row">
					<dt><?php esc_html_e( 'MCP Adapter', 'inhale-mcp-abilities' ); ?></dt>
					<dd>
						<?php if ( $adapter['running'] ) : ?>
							<span class="inhale-status__ok"><?php esc_html_e( 'Running', 'inhale-mcp-abilities' ); ?></span>
							<?php
							$bits = array();
							if ( '' !== $adapter['version'] ) {
								/* translators: %s: MCP Adapter version. */
								$bits[] = sprintf( __( 'version %s', 'inhale-mcp-abilities' ), $adapter['version'] );
							}
							if ( '' !== $adapter['provider'] ) {
								/* translators: %s: name of the plugin that loads the MCP Adapter. */
								$bits[] = sprintf( __( 'loaded by %s', 'inhale-mcp-abilities' ), $adapter['provider'] );
							}
							if ( $bits ) {
								echo ' <span class="inhale-status__note">(' . esc_html( implode( ', ', $bits ) ) . ')</span>';
							}
							?>
						<?php else : ?>
							<span class="inhale-status__warn"><?php esc_html_e( 'Not running', 'inhale-mcp-abilities' ); ?></span>
							<span class="inhale-status__note"><?php esc_html_e( 'Your choices are saved, and AI clients can reach them once an MCP Adapter runs.', 'inhale-mcp-abilities' ); ?></span>
							<a href="<?php echo esc_url( $install ); ?>"><?php esc_html_e( 'Get MCP Adapter', 'inhale-mcp-abilities' ); ?></a>
						<?php endif; ?>
					</dd>
				</div>
				<div class="inhale-status__row">
					<dt><?php esc_html_e( 'Endpoint', 'inhale-mcp-abilities' ); ?></dt>
					<dd><code><?php echo esc_html( $endpoint ); ?></code></dd>
				</div>
				<div class="inhale-status__row">
					<dt><?php esc_html_e( 'Application Passwords', 'inhale-mcp-abilities' ); ?></dt>
					<dd>
						<?php if ( $app_pw ) : ?>
							<span class="inhale-status__ok"><?php esc_html_e( 'Available', 'inhale-mcp-abilities' ); ?></span>
						<?php elseif ( ! $https ) : ?>
							<span class="inhale-status__warn"><?php esc_html_e( 'Not available', 'inhale-mcp-abilities' ); ?></span>
							<span class="inhale-status__note"><?php esc_html_e( 'WordPress offers them only on sites served over HTTPS (or local sites).', 'inhale-mcp-abilities' ); ?></span>
						<?php else : ?>
							<span class="inhale-status__warn"><?php esc_html_e( 'Turned off', 'inhale-mcp-abilities' ); ?></span>
							<span class="inhale-status__note"><?php esc_html_e( 'A security plugin or your host disabled them, so clients cannot sign in with one.', 'inhale-mcp-abilities' ); ?></span>
						<?php endif; ?>
					</dd>
				</div>
				<div class="inhale-status__row">
					<dt><?php esc_html_e( 'Abilities', 'inhale-mcp-abilities' ); ?></dt>
					<dd>
						<?php
						printf(
							/* translators: 1: abilities exposed to MCP, 2: abilities registered on the site. */
							esc_html__( '%1$d of %2$d exposed to MCP', 'inhale-mcp-abilities' ),
							(int) $own_exposed,
							(int) $own_total
						);
						?>
					</dd>
				</div>
			</dl>
		</section>
		<?php
	}

	/**
	 * Ask for a rating once, a week after the first saved choice, and never again after a dismissal.
	 *
	 * Shown only on this page, to administrators, with a plain dismiss link.
	 *
	 * @since 0.6.0
	 */
	private function render_review_request() {
		$first_saved = (int) get_option( 'respira_inhale_first_saved_at', 0 );
		if ( $first_saved <= 0 || ( time() - $first_saved ) < WEEK_IN_SECONDS ) {
			return;
		}
		$user_id = get_current_user_id();
		if ( ! $user_id || get_user_meta( $user_id, 'respira_inhale_review_dismissed', true ) ) {
			return;
		}
		$dismiss = wp_nonce_url( add_query_arg( 'inhale_review', 'dismiss', menu_page_url( self::MENU_SLUG, false ) ), 'respira_inhale_review' );
		?>
		<div class="notice notice-info inhale-notice inhale-review-request">
			<p>
				<?php esc_html_e( 'If Inhale saves you some PHP, a short rating on WordPress.org helps other site owners find it. It takes a minute.', 'inhale-mcp-abilities' ); ?>
				<a href="https://wordpress.org/support/plugin/inhale-mcp-abilities/reviews/#new-post" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Rate Inhale', 'inhale-mcp-abilities' ); ?></a>
				&nbsp;&middot;&nbsp;
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'No thanks', 'inhale-mcp-abilities' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Render the tablenav row (bulk actions + Apply on the left, pagination
	 * on the right). Used both above and below the abilities table.
	 *
	 * @param string             $position 'top' or 'bottom'.
	 * @param array<string, int> $counts   View counts for displaying-num.
	 */
	private function render_tablenav( $position, $counts ) {
		$position    = ( 'top' === $position ) ? 'top' : 'bottom';
		$total       = (int) $counts['all'];
		$select_name = ( 'top' === $position ) ? 'action' : 'action2';
		$select_id   = 'inhale-bulk-action-' . $position;
		?>
		<div class="tablenav <?php echo esc_attr( $position ); ?>">
			<div class="alignleft actions bulkactions">
				<label for="<?php echo esc_attr( $select_id ); ?>" class="screen-reader-text"><?php esc_html_e( 'Select bulk action', 'inhale-mcp-abilities' ); ?></label>
				<select id="<?php echo esc_attr( $select_id ); ?>" name="<?php echo esc_attr( $select_name ); ?>" class="inhale-bulk-action">
					<option value="-1"><?php esc_html_e( 'Bulk actions', 'inhale-mcp-abilities' ); ?></option>
					<option value="inhale"><?php esc_html_e( 'Inhale', 'inhale-mcp-abilities' ); ?></option>
					<option value="exhale"><?php esc_html_e( 'Exhale', 'inhale-mcp-abilities' ); ?></option>
				</select>
				<button type="submit" class="button action inhale-bulk-apply" name="<?php echo esc_attr( 'top' === $position ? 'apply-top' : 'apply-bottom' ); ?>"><?php esc_html_e( 'Apply', 'inhale-mcp-abilities' ); ?></button>
			</div>
			<div class="tablenav-pages">
				<span class="displaying-num">
					<span class="inhale-visible-count"><?php echo (int) $total; ?></span>
					<?php
					/* translators: %d: total count of registered abilities. */
					echo esc_html( sprintf( _n( 'of %d ability', 'of %d abilities', $total, 'inhale-mcp-abilities' ), $total ) );
					?>
				</span>
				<span class="pagination-links" data-position="<?php echo esc_attr( $position ); ?>">
					<button type="button" class="button inhale-pg-first" aria-label="<?php esc_attr_e( 'First page', 'inhale-mcp-abilities' ); ?>" disabled>&laquo;</button>
					<button type="button" class="button inhale-pg-prev" aria-label="<?php esc_attr_e( 'Previous page', 'inhale-mcp-abilities' ); ?>" disabled>&lsaquo;</button>
					<span class="paging-input">
						<label class="screen-reader-text" for="inhale-current-page-<?php echo esc_attr( $position ); ?>"><?php esc_html_e( 'Current page', 'inhale-mcp-abilities' ); ?></label>
						<input
							class="current-page inhale-pg-current"
							id="inhale-current-page-<?php echo esc_attr( $position ); ?>"
							type="text"
							value="1"
							size="2"
							autocomplete="off"
							aria-describedby="inhale-pg-total-<?php echo esc_attr( $position ); ?>"
						/>
						<span class="tablenav-paging-text">
							<?php esc_html_e( 'of', 'inhale-mcp-abilities' ); ?>
							<span class="total-pages inhale-pg-total" id="inhale-pg-total-<?php echo esc_attr( $position ); ?>">1</span>
						</span>
					</span>
					<button type="button" class="button inhale-pg-next" aria-label="<?php esc_attr_e( 'Next page', 'inhale-mcp-abilities' ); ?>" disabled>&rsaquo;</button>
					<button type="button" class="button inhale-pg-last" aria-label="<?php esc_attr_e( 'Last page', 'inhale-mcp-abilities' ); ?>" disabled>&raquo;</button>
				</span>
				<label class="inhale-perpage">
					<span class="screen-reader-text"><?php esc_html_e( 'Items per page', 'inhale-mcp-abilities' ); ?></span>
					<select class="inhale-pg-perpage">
						<option value="20">20</option>
						<option value="50" selected>50</option>
						<option value="100">100</option>
						<option value="0"><?php esc_html_e( 'All', 'inhale-mcp-abilities' ); ?></option>
					</select>
					<span><?php esc_html_e( 'per page', 'inhale-mcp-abilities' ); ?></span>
				</label>
			</div>
		</div>
		<?php
	}

	/**
	 * Render one table row.
	 *
	 * @param array<string, mixed> $a       Normalized ability row.
	 * @param bool                 $checked Whether the ability is currently inhaled.
	 */
	private function render_row( $a, $checked ) {
		$name        = (string) $a['name'];
		$desc        = (string) $a['description'];
		$source      = (string) $a['source'];
		$annotations = (array) $a['annotations'];
		$managed     = ! empty( $a['managed'] );
		$inferred    = ! empty( $a['inferred'] );

		$is_destructive = in_array( 'destructive', $annotations, true );

		$row_classes = array();
		if ( $managed ) {
			$row_classes[] = 'disabled';
		}

		$row_attrs  = array();
		$row_attrs[] = 'data-source="' . esc_attr( $source ) . '"';
		$row_attrs[] = 'data-annot="' . esc_attr( implode( ' ', $annotations ) ) . '"';
		$row_attrs[] = 'data-inhaled="' . ( $checked ? 'true' : 'false' ) . '"';
		if ( $managed ) {
			$row_attrs[] = 'data-managed="true"';
		}

		$cb_id        = 'respira_inhale_ab_' . md5( $name );
		$single_label = $checked
			? /* translators: %s: ability name. */ __( 'Select %s for bulk action (currently inhaled)', 'inhale-mcp-abilities' )
			: /* translators: %s: ability name. */ __( 'Select %s for bulk action (currently not inhaled)', 'inhale-mcp-abilities' );
		$row_class    = implode( ' ', $row_classes );
		?>
		<tr class="<?php echo esc_attr( $row_class ); ?>" <?php echo implode( ' ', $row_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- each $row_attrs entry was built with esc_attr() above. ?>>
			<td class="col-check check-column">
				<?php if ( $managed ) : ?>
					<input type="checkbox" disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s: ability name. */ __( 'Managed by mcp-adapter, cannot select: %s', 'inhale-mcp-abilities' ), $name ) ); ?>" />
				<?php else : ?>
					<input type="checkbox"
						id="<?php echo esc_attr( $cb_id ); ?>"
						class="inhale-ability-checkbox"
						name="abilities[]"
						value="<?php echo esc_attr( $name ); ?>"
						data-destructive="<?php echo esc_attr( $is_destructive ? '1' : '0' ); ?>"
						aria-label="<?php echo esc_attr( sprintf( $single_label, $name ) ); ?>" />
				<?php endif; ?>
			</td>
			<td class="col-ability">
				<span class="ability-name"><?php echo esc_html( $name ); ?></span>
				<?php if ( ! $managed ) : ?>
					<div class="row-actions">
						<?php if ( $checked ) : ?>
							<span class="exhale"><a href="#" class="inhale-row-action" data-action="exhale" data-ability="<?php echo esc_attr( $name ); ?>"><?php esc_html_e( 'Exhale', 'inhale-mcp-abilities' ); ?></a></span>
						<?php else : ?>
							<span class="inhale"><a href="#" class="inhale-row-action" data-action="inhale" data-ability="<?php echo esc_attr( $name ); ?>" data-destructive="<?php echo esc_attr( $is_destructive ? '1' : '0' ); ?>"><?php esc_html_e( 'Inhale', 'inhale-mcp-abilities' ); ?></a></span>
						<?php endif; ?>
					</div>
				<?php endif; ?>
			</td>
			<td class="col-source"><span class="source-name" title="<?php echo esc_attr( $source ); ?>"><?php echo esc_html( $source ); ?></span></td>
			<td class="col-desc">
				<?php if ( $managed ) : ?>
					<span class="ability-desc"><?php esc_html_e( '(managed by mcp-adapter)', 'inhale-mcp-abilities' ); ?></span>
				<?php else : ?>
					<span class="ability-desc" title="<?php echo esc_attr( $desc ); ?>"><?php echo esc_html( $desc ); ?></span>
				<?php endif; ?>
			</td>
			<td class="col-status">
				<?php if ( $managed ) : ?>
					<label class="inhale-toggle" title="<?php esc_attr_e( 'Managed by mcp-adapter — cannot toggle', 'inhale-mcp-abilities' ); ?>">
						<input type="checkbox" disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s: ability name. */ __( 'Managed by mcp-adapter: %s', 'inhale-mcp-abilities' ), $name ) ); ?>" />
						<span class="inhale-toggle-slider"></span>
					</label>
				<?php else : ?>
					<label class="inhale-toggle">
						<input type="checkbox"
							class="inhale-toggle-input"
							data-ability="<?php echo esc_attr( $name ); ?>"
							data-destructive="<?php echo esc_attr( $is_destructive ? '1' : '0' ); ?>"
							<?php checked( $checked ); ?>
							aria-label="<?php echo esc_attr( $checked
								? sprintf( /* translators: %s: ability name. */ __( 'Exhale %s', 'inhale-mcp-abilities' ), $name )
								: sprintf( /* translators: %s: ability name. */ __( 'Inhale %s', 'inhale-mcp-abilities' ), $name )
							); ?>" />
						<span class="inhale-toggle-slider"></span>
					</label>
				<?php endif; ?>
			</td>
			<td class="col-annot">
				<?php if ( empty( $annotations ) ) : ?>
					<span class="annot-none"><?php esc_html_e( 'no annotations', 'inhale-mcp-abilities' ); ?></span>
				<?php else : ?>
					<?php $inferred_class = $inferred ? ' inferred' : ''; ?>
					<?php foreach ( $annotations as $flag ) : ?>
						<?php if ( 'destructive' === $flag ) : ?>
							<span class="annot destructive<?php echo esc_attr( $inferred_class ); ?>" <?php if ( $inferred ) : ?>title="<?php esc_attr_e( 'Inferred from name. The registering plugin did not declare this annotation.', 'inhale-mcp-abilities' ); ?>"<?php endif; ?>><svg class="glyph" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true"><path d="M5 1.4L9 8.6H1z"/></svg><?php esc_html_e( 'destructive', 'inhale-mcp-abilities' ); ?><?php if ( $inferred ) : ?><span class="annot-inferred-mark" aria-hidden="true">*</span><?php endif; ?></span>
						<?php else : ?>
							<span class="annot neutral<?php echo esc_attr( $inferred_class ); ?>" <?php if ( $inferred ) : ?>title="<?php esc_attr_e( 'Inferred from name. The registering plugin did not declare this annotation.', 'inhale-mcp-abilities' ); ?>"<?php endif; ?>><?php echo esc_html( $flag ); ?><?php if ( $inferred ) : ?><span class="annot-inferred-mark" aria-hidden="true">*</span><?php endif; ?></span>
						<?php endif; ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Build the unique source list for the filter popover.
	 *
	 * @param array<int, array<string, mixed>> $abilities Discovered abilities.
	 * @return array<int, string>
	 */
	private function build_source_list( $abilities ) {
		$seen = array();
		foreach ( $abilities as $a ) {
			$source = isset( $a['source'] ) ? (string) $a['source'] : '';
			if ( '' === $source ) {
				continue;
			}
			$seen[ $source ] = true;
		}
		$list = array_keys( $seen );
		sort( $list );
		return $list;
	}

	/**
	 * Build a summary list of sources with their ability counts. Used in the
	 * card above the abilities table.
	 *
	 * @param array<int, array<string, mixed>> $abilities Normalized rows.
	 * @return array<int, array{label: string, count: int, url: string}>
	 */
	private function build_source_summary( $abilities ) {
		$counts = array();
		foreach ( $abilities as $a ) {
			$source = isset( $a['source'] ) ? (string) $a['source'] : '';
			if ( '' === $source ) {
				continue;
			}
			if ( ! isset( $counts[ $source ] ) ) {
				$counts[ $source ] = 0;
			}
			++$counts[ $source ];
		}

		$rows = array();
		foreach ( $counts as $label => $count ) {
			$rows[] = array(
				'label' => $label,
				'count' => $count,
				'url'   => $this->get_source_admin_url( $label ),
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				if ( $a['count'] === $b['count'] ) {
					return strnatcasecmp( $a['label'], $b['label'] );
				}
				return $b['count'] - $a['count'];
			}
		);

		return $rows;
	}

	/**
	 * Best-effort resolution of a source label to its wp-admin destination.
	 *
	 * Returns an admin URL for known plugins, or the Plugins listing
	 * (filtered by plugin name) as a generic fallback. Returns an empty
	 * string for sources that don't have an admin home (e.g. core).
	 *
	 * @param string $source_label The human-readable source name.
	 * @return string
	 */
	private function get_source_admin_url( $source_label ) {
		$known = array(
			'Respira for WordPress' => admin_url( 'admin.php?page=respira' ),
			'Respira WooCommerce'   => admin_url( 'admin.php?page=respira' ),
			'MCP Adapter (managed)' => admin_url( 'options-general.php?page=mcp-adapter' ),
			'AI Engine'             => admin_url( 'admin.php?page=meowapps-main-menu' ),
			'WPForms'               => admin_url( 'admin.php?page=wpforms-overview' ),
			'Yoast SEO'             => admin_url( 'admin.php?page=wpseo_dashboard' ),
		);

		if ( isset( $known[ $source_label ] ) ) {
			return $known[ $source_label ];
		}

		if ( __( 'WordPress core', 'inhale-mcp-abilities' ) === $source_label ) {
			return '';
		}

		/**
		 * Filter the admin URL for a source plugin label.
		 *
		 * @param string $url    Default URL (Plugins listing filtered by label).
		 * @param string $source Source label.
		 */
		return apply_filters(
			'respira_inhale_source_admin_url',
			admin_url( 'plugins.php?plugin_status=active&s=' . rawurlencode( $source_label ) ),
			$source_label
		);
	}

	/**
	 * Read the saved option, normalised to a list of strings.
	 *
	 * @return array<int, string>
	 */
	private function get_exposed() {
		$raw = get_option( RESPIRA_INHALE_OPTION_NAME, array() );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $entry ) {
			if ( is_string( $entry ) && '' !== $entry ) {
				$out[] = $entry;
			}
		}
		return $out;
	}
}
