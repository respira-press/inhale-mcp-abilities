<?php
/**
 * Renders Settings > Inhale MCP Abilities and the Settings API plumbing
 * that backs it.
 *
 * @package Inhale_MCP_Abilities
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Inhale_Settings_Page: admin menu, option registration, and page render.
 */
class Inhale_Settings_Page {

	const MENU_SLUG     = 'inhale-mcp-abilities';
	const OPTION_GROUP  = 'inhale_mcp_abilities_group';
	const CAPABILITY    = 'manage_options';
	const MANAGED_NS    = 'mcp-adapter/';
	const DEFAULT_SERVER_ROUTE = '/wp-json/mcp/mcp-adapter-default-server';

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
		add_action( 'admin_init', array( $this, 'register_setting' ), 10, 0 );
		add_action( 'admin_head', array( $this, 'inject_menu_icon_css' ), 10, 0 );
	}

	/**
	 * Register the Settings sub-menu entry.
	 */
	public function register_menu() {
		add_options_page(
			__( 'Inhale MCP Abilities', 'inhale-mcp-abilities' ),
			__( 'Inhale MCP Abilities', 'inhale-mcp-abilities' ),
			self::CAPABILITY,
			self::MENU_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Inject the Inhale dot-grid mark next to the submenu item and on the
	 * Plugins screen row, alongside admin-bar fallbacks for older themes.
	 *
	 * add_options_page() doesn't accept an icon parameter for sub-pages, so
	 * we deliver the mark as a CSS background-image (URL-encoded SVG) on a
	 * ::before pseudo-element scoped to our submenu anchor.
	 */
	public function inject_menu_icon_css() {
		$svg_url = $this->get_icon_data_url();
		$css     = '#adminmenu .wp-submenu a[href$="page=' . esc_attr( self::MENU_SLUG ) . '"]::before{'
			. 'content:"";'
			. 'display:inline-block;'
			. 'width:14px;'
			. 'height:14px;'
			. 'background-image:url(\'' . $svg_url . '\');'
			. 'background-size:contain;'
			. 'background-repeat:no-repeat;'
			. 'background-position:center;'
			. 'vertical-align:-3px;'
			. 'margin-right:6px;'
			. 'border-radius:2px;'
			. 'flex-shrink:0;'
			. '}';
		echo '<style>' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Build a URL-encoded data: URL for the Inhale dot-grid mark.
	 *
	 * Kept inline (rather than referencing the assets/images/icon.svg file)
	 * so the menu marker loads with the admin head and doesn't trigger a
	 * second HTTP request.
	 *
	 * @return string
	 */
	private function get_icon_data_url() {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
			. '<g fill="#a8a29e">'
			. '<circle cx="20" cy="20" r="4"/><circle cx="32" cy="20" r="4"/><circle cx="44" cy="20" r="4"/><circle cx="56" cy="20" r="4"/><circle cx="68" cy="20" r="4"/><circle cx="80" cy="20" r="4"/>'
			. '<circle cx="20" cy="32" r="4"/><circle cx="32" cy="32" r="4"/><circle cx="44" cy="32" r="4"/><circle cx="56" cy="32" r="4"/><circle cx="68" cy="32" r="4"/><circle cx="80" cy="32" r="4"/>'
			. '<circle cx="20" cy="44" r="4"/><circle cx="32" cy="44" r="4"/><circle cx="44" cy="44" r="4"/><circle cx="56" cy="44" r="4"/><circle cx="68" cy="44" r="4"/><circle cx="80" cy="44" r="4"/>'
			. '<circle cx="20" cy="56" r="4"/><circle cx="32" cy="56" r="4"/><circle cx="44" cy="56" r="4"/><circle cx="56" cy="56" r="4"/><circle cx="68" cy="56" r="4"/><circle cx="80" cy="56" r="4"/>'
			. '<circle cx="20" cy="68" r="4"/><circle cx="32" cy="68" r="4"/><circle cx="44" cy="68" r="4"/><circle cx="56" cy="68" r="4"/><circle cx="68" cy="68" r="4"/><circle cx="80" cy="68" r="4"/>'
			. '<circle cx="20" cy="80" r="4"/><circle cx="32" cy="80" r="4"/><circle cx="44" cy="80" r="4"/><circle cx="56" cy="80" r="4"/><circle cx="68" cy="80" r="4"/><circle cx="80" cy="80" r="4"/>'
			. '</g>'
			. '<g fill="#34d399">'
			. '<circle cx="32" cy="32" r="4"/><circle cx="44" cy="56" r="4"/><circle cx="56" cy="20" r="4"/><circle cx="68" cy="68" r="4"/><circle cx="20" cy="80" r="4"/>'
			. '</g>'
			. '</svg>';
		return 'data:image/svg+xml;utf8,' . rawurlencode( $svg );
	}

	/**
	 * Register the option with the Settings API.
	 */
	public function register_setting() {
		register_setting(
			self::OPTION_GROUP,
			INHALE_OPTION_NAME,
			array(
				'type'              => 'array',
				'description'       => __( 'List of ability names exposed to the default MCP server.', 'inhale-mcp-abilities' ),
				'sanitize_callback' => array( $this, 'sanitize_option' ),
				'default'           => array(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize the submitted option value.
	 *
	 * Accepts only string entries that match an ability name currently
	 * registered on this site, and that are not in the managed mcp-adapter
	 * namespace.
	 *
	 * @param mixed $value Submitted value.
	 * @return array<int, string>
	 */
	public function sanitize_option( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$known = array_map(
			static function ( $a ) {
				return isset( $a['name'] ) ? (string) $a['name'] : '';
			},
			$this->discover_abilities()
		);
		$known = array_filter( $known );

		$out = array();
		foreach ( $value as $entry ) {
			if ( ! is_string( $entry ) ) {
				continue;
			}
			$entry = sanitize_text_field( wp_unslash( $entry ) );
			if ( '' === $entry ) {
				continue;
			}
			if ( 0 === strpos( $entry, self::MANAGED_NS ) ) {
				continue;
			}
			if ( ! in_array( $entry, $known, true ) ) {
				continue;
			}
			if ( in_array( $entry, $out, true ) ) {
				continue;
			}
			$out[] = $entry;
		}

		return $out;
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
		$managed     = ( 0 === strpos( $name, self::MANAGED_NS ) );

		return array(
			'name'        => $name,
			'label'       => $label,
			'description' => $description,
			'source'      => $this->get_source_plugin( $name ),
			'annotations' => $annotations,
			'managed'     => $managed,
		);
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
			'core'        => __( 'WordPress core', 'inhale-mcp-abilities' ),
			'wp'          => __( 'WordPress core', 'inhale-mcp-abilities' ),
			'mcp-adapter' => __( 'MCP Adapter (managed)', 'inhale-mcp-abilities' ),
			'respira'     => __( 'Respira for WordPress', 'inhale-mcp-abilities' ),
			'ai-engine'   => __( 'AI Engine', 'inhale-mcp-abilities' ),
			'wpforms'     => __( 'WPForms', 'inhale-mcp-abilities' ),
			'yoast'       => __( 'Yoast SEO', 'inhale-mcp-abilities' ),
		);

		/**
		 * Filter the namespace-to-label map for the source column.
		 *
		 * @param array<string, string> $map Default map.
		 */
		return apply_filters( 'inhale_mcp_abilities_source_labels', $map );
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
			wp_die( esc_html__( 'You do not have permission to access this page.', 'inhale-mcp-abilities' ) );
		}

		$abilities       = $this->discover_abilities();
		$exposed         = $this->get_exposed();
		$counts          = $this->build_counts( $abilities, $exposed );
		$sources         = $this->build_source_list( $abilities );
		$source_summary  = $this->build_source_summary( $abilities );
		$endpoint        = esc_url( home_url( self::DEFAULT_SERVER_ROUTE ) );

		?>
		<div class="wrap inhale-wrap" data-theme="light">
			<?php settings_errors(); ?>

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
						<span class="inhale-h1-text"><?php esc_html_e( 'Inhale: MCP Abilities', 'inhale-mcp-abilities' ); ?></span>
					</h1>
					<p class="page-desc"><?php esc_html_e( 'Decide which registered abilities are visible to the default MCP server.', 'inhale-mcp-abilities' ); ?></p>
					<span class="accent-line" aria-hidden="true"></span>
				</div>
				<div class="page-head-tools">
					<a class="docs-link"
						href="https://respira.press/docs/inhale-mcp-abilities/"
						target="_blank"
						rel="noopener noreferrer">
						<?php esc_html_e( 'Documentation', 'inhale-mcp-abilities' ); ?>
						<svg viewBox="0 0 11 11" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
							<path d="M4 2H2v7h7V7"/>
							<path d="M6 2h3v3"/>
							<path d="M9 2L5 6"/>
						</svg>
					</a>
					<button type="button"
						class="theme-toggle"
						id="inhaleThemeToggle"
						aria-label="<?php esc_attr_e( 'Toggle light or dark mode for the Inhale plugin', 'inhale-mcp-abilities' ); ?>"
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

			<form method="post" action="options.php" id="inhaleAbilitiesForm">
				<?php settings_fields( self::OPTION_GROUP ); ?>

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

				<div class="save-row">
					<div class="summary">
						<strong class="inhale-inhaled-count"><?php echo (int) $counts['inhaled']; ?></strong>
						<?php esc_html_e( 'abilities currently inhaled', 'inhale-mcp-abilities' ); ?>
						<span class="inhale-dirty-indicator" id="inhaleDirtyIndicator" hidden>
							<span class="inhale-dirty-dot" aria-hidden="true"></span>
							<?php esc_html_e( 'Unsaved changes', 'inhale-mcp-abilities' ); ?>
						</span>
					</div>
					<?php submit_button( __( 'Save changes', 'inhale-mcp-abilities' ), 'primary large', 'submit', false ); ?>
				</div>
			</form>

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
					<summary><?php esc_html_e( 'Connect with WP-CLI (STDIO)', 'inhale-mcp-abilities' ); ?></summary>
					<div class="disclosure-body">
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
        "mcp",
        "stdio",
        "--server=mcp-adapter-default-server"
      ]
    }
  }
}</pre>
					</div>
				</details>

				<details class="disclosure">
					<summary><?php esc_html_e( 'Connect with HTTP transport', 'inhale-mcp-abilities' ); ?></summary>
					<div class="disclosure-body">
						<ol>
							<li><?php
								echo wp_kses(
									__( 'Go to <em>Users → Profile → Application Passwords</em>.', 'inhale-mcp-abilities' ),
									array( 'em' => array() )
								);
							?></li>
							<li><?php
								echo wp_kses(
									/* translators: %s: the application password name `mcp-client` wrapped in <code> tags. */
									sprintf( __( 'Create a new application password named %s.', 'inhale-mcp-abilities' ), '<code>mcp-client</code>' ),
									array( 'code' => array() )
								);
							?></li>
							<li><?php esc_html_e( 'Copy the generated token (24 characters, four groups of six).', 'inhale-mcp-abilities' ); ?></li>
							<li><?php esc_html_e( 'Add the configuration below to your MCP client.', 'inhale-mcp-abilities' ); ?></li>
						</ol>
<pre>{
  "mcpServers": {
    "wordpress-inhale": {
      "transport": "http",
      "url": "<?php echo esc_html( $endpoint ); ?>",
      "auth": {
        "type": "basic",
        "username": "admin",
        "password": "xxxx xxxx xxxx xxxx xxxx xxxx"
      }
    }
  }
}</pre>
					</div>
				</details>
			</section>

			<hr class="divider"/>

			<section class="section" aria-labelledby="inhale-about-h">
				<h2 id="inhale-about-h"><?php esc_html_e( 'About Inhale', 'inhale-mcp-abilities' ); ?></h2>
				<p><?php esc_html_e( 'Inhale is a settings-only utility. It does not run MCP servers, transports, or authentication. Those are handled by the official WordPress MCP Adapter, which Inhale extends.', 'inhale-mcp-abilities' ); ?></p>
				<p><?php esc_html_e( 'Every ability you inhale still runs its own permission checks before execution. Inhale controls visibility, not authorization.', 'inhale-mcp-abilities' ); ?></p>
				<p class="muted"><?php esc_html_e( 'Model Context Protocol (MCP) is an open specification originally developed by Anthropic. Inhale is a third-party plugin and is not affiliated with, endorsed by, or sponsored by Anthropic. Respira is an independent company.', 'inhale-mcp-abilities' ); ?></p>
			</section>

			<hr class="divider"/>

			<p class="muted inhale-respira-footer"><?php
				echo wp_kses(
					/* translators: 1: link to respira.press, 2: link to respira.press. */
					sprintf(
						__( 'Inhale is built by Respira. Respira ships AI infrastructure for WordPress, including %1$s, the safety layer for AI-driven edits across 12 page builders. Learn more at %2$s.', 'inhale-mcp-abilities' ),
						'<a href="https://respira.press" target="_blank" rel="noopener noreferrer">Respira for WordPress</a>',
						'<a href="https://respira.press" target="_blank" rel="noopener noreferrer">respira.press</a>'
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
	 * Render the tablenav row (quick actions on the left, pagination on the
	 * right). Used both above and below the abilities table.
	 *
	 * @param string             $position 'top' or 'bottom'.
	 * @param array<string, int> $counts   View counts for displaying-num.
	 */
	private function render_tablenav( $position, $counts ) {
		$position    = ( 'top' === $position ) ? 'top' : 'bottom';
		$total       = (int) $counts['all'];
		$current_in  = (int) $counts['inhaled'];
		?>
		<div class="tablenav <?php echo esc_attr( $position ); ?>">
			<div class="inhale-quickactions">
				<button
					type="button"
					class="button inhale-quickaction"
					data-action="inhale"
					title="<?php esc_attr_e( 'Inhale every ability matching the current filter. You will still need to click Save changes to persist.', 'inhale-mcp-abilities' ); ?>">
					<?php esc_html_e( 'Inhale all filtered', 'inhale-mcp-abilities' ); ?>
				</button>
				<button
					type="button"
					class="button inhale-quickaction"
					data-action="exhale"
					title="<?php esc_attr_e( 'Exhale every ability matching the current filter. You will still need to click Save changes to persist.', 'inhale-mcp-abilities' ); ?>">
					<?php esc_html_e( 'Exhale all filtered', 'inhale-mcp-abilities' ); ?>
				</button>
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

		$is_destructive = in_array( 'destructive', $annotations, true );

		$row_classes = array();
		if ( $managed ) {
			$row_classes[] = 'disabled';
		}

		$row_attrs  = array();
		$row_attrs[] = 'data-source="' . esc_attr( $source ) . '"';
		$row_attrs[] = 'data-annot="' . esc_attr( implode( ' ', $annotations ) ) . '"';
		if ( $managed ) {
			$row_attrs[] = 'data-managed="true"';
		}

		$cb_id = 'inhale_ab_' . md5( $name );
		?>
		<tr<?php echo $row_classes ? ' class="' . esc_attr( implode( ' ', $row_classes ) ) . '"' : ''; ?> <?php echo implode( ' ', $row_attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- pre-escaped above ?>>
			<td class="col-check check-column">
				<?php if ( $managed ) : ?>
					<input type="checkbox" disabled aria-label="<?php echo esc_attr( sprintf( /* translators: %s: ability name. */ __( 'Managed by mcp-adapter, cannot toggle: %s', 'inhale-mcp-abilities' ), $name ) ); ?>" />
				<?php else : ?>
					<input type="checkbox"
						id="<?php echo esc_attr( $cb_id ); ?>"
						class="inhale-ability-checkbox"
						name="<?php echo esc_attr( INHALE_OPTION_NAME ); ?>[]"
						value="<?php echo esc_attr( $name ); ?>"
						data-destructive="<?php echo $is_destructive ? '1' : '0'; ?>"
						<?php checked( $checked ); ?>
						aria-label="<?php echo esc_attr( sprintf( $checked ? /* translators: %s: ability name. */ __( 'Inhaled: %s', 'inhale-mcp-abilities' ) : /* translators: %s: ability name. */ __( 'Inhale %s', 'inhale-mcp-abilities' ), $name ) ); ?>" />
				<?php endif; ?>
			</td>
			<td class="col-ability">
				<span class="ability-name"><?php echo esc_html( $name ); ?></span>
				<?php if ( $is_destructive && ! $managed ) : ?>
					<span class="dest-note"><?php esc_html_e( 'Triggers a single browser confirmation on check.', 'inhale-mcp-abilities' ); ?></span>
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
					<span class="status-pill managed"><?php esc_html_e( 'Managed', 'inhale-mcp-abilities' ); ?></span>
				<?php elseif ( $checked ) : ?>
					<span class="status-pill inhaled"><?php esc_html_e( 'Inhaled', 'inhale-mcp-abilities' ); ?></span>
				<?php else : ?>
					<span class="status-empty" aria-label="<?php esc_attr_e( 'Not inhaled', 'inhale-mcp-abilities' ); ?>">—</span>
				<?php endif; ?>
			</td>
			<td class="col-annot">
				<?php if ( empty( $annotations ) ) : ?>
					<span class="annot-none"><?php esc_html_e( 'no annotations', 'inhale-mcp-abilities' ); ?></span>
				<?php else : ?>
					<?php foreach ( $annotations as $flag ) : ?>
						<?php if ( 'destructive' === $flag ) : ?>
							<span class="annot destructive"><svg class="glyph" viewBox="0 0 10 10" fill="none" stroke="currentColor" stroke-width="1" stroke-linejoin="round" aria-hidden="true"><path d="M5 1.4L9 8.6H1z"/></svg><?php esc_html_e( 'destructive', 'inhale-mcp-abilities' ); ?></span>
						<?php else : ?>
							<span class="annot neutral"><?php echo esc_html( $flag ); ?></span>
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
			'inhale_mcp_abilities_source_admin_url',
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
		$raw = get_option( INHALE_OPTION_NAME, array() );
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
