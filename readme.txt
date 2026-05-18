=== Inhale: MCP Abilities ===
Contributors: respira
Tags: mcp, ai, abilities, model context protocol, ai infrastructure
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.3.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A deliberate, considered way to expose registered WordPress abilities to the default MCP server. Settings-only. Safety-aware.

== Description ==

Inhale: MCP Abilities is a small, focused utility that solves one problem: the default WordPress MCP server (provided by the official MCP Adapter plugin) does not expose any registered abilities by default. Site administrators have to write PHP filters to opt each ability into the public MCP surface.

This is the workaround pattern documented in WordPress contributor blog posts and developer guides since the MCP Adapter shipped. Inhale: MCP Abilities replaces the PHP-filter workaround with a simple settings page.

Once installed and activated, you'll find a new page at Settings &gt; Inhale: MCP Abilities where you can check off the abilities you want exposed to your default MCP server.

= What the Inhale: MCP Abilities plugin does =

* Lists every registered WordPress ability across all active plugins and themes
* Lets you select which abilities are exposed to the default MCP server with simple checkboxes
* Shows annotation metadata (read-only, destructive, idempotent) on each ability so you can make informed decisions
* Requires explicit confirmation when you inhale an ability marked as destructive
* Provides connection info for popular MCP clients (Claude Desktop, Cursor, Claude Code)
* Respects each ability's own permission_callback. The Inhale: MCP Abilities plugin controls visibility, not authorization.

= What the Inhale: MCP Abilities plugin doesn't do =

* Inhale: MCP Abilities does not run any MCP servers, transports, or authentication. Those are handled by the official MCP Adapter plugin, which the Inhale: MCP Abilities plugin extends.
* Inhale: MCP Abilities does not register any abilities of its own. It only toggles visibility of abilities other plugins have registered.
* Inhale: MCP Abilities does not phone home, collect telemetry, or make external network requests.

= Requirements =

* WordPress 6.8 or later (Abilities API in core since 6.9; 6.8 requires the Abilities API plugin)
* PHP 7.4 or later
* The official WordPress MCP Adapter plugin installed and active

= About MCP =

Model Context Protocol (MCP) is an open specification originally developed by Anthropic. Inhale: MCP Abilities is a third-party plugin and is not affiliated with, endorsed by, or sponsored by Anthropic. Respira is an independent company.

= About Respira =

The Inhale: MCP Abilities plugin is built and maintained by Respira, which ships AI infrastructure for WordPress. The main product is Respira for WordPress, a safety layer that registers 130+ abilities across 12 page builders (Elementor, Bricks, Divi, Beaver Builder, Oxygen, Breakdance and 6 more) with snapshot-before-write protection, render validation and one-click rollback. Inhale: MCP Abilities is a free utility offered to the WordPress community. Learn more at https://respira.press/inhale.

== Installation ==

1. Install and activate the official WordPress MCP Adapter plugin (https://github.com/WordPress/mcp-adapter)
2. Install Inhale: MCP Abilities from the WordPress plugin directory or by uploading the plugin zip
3. Activate the Inhale: MCP Abilities plugin
4. Navigate to Settings &gt; Inhale: MCP Abilities
5. Check the abilities you want exposed to your default MCP server
6. Save changes
7. Connect your MCP client (Claude Desktop, Cursor, Claude Code, or any MCP-compatible AI assistant) to the endpoint shown on the Inhale: MCP Abilities settings page

== Frequently Asked Questions ==

= Do I need to write any code to use the Inhale: MCP Abilities plugin? =

No. Inhale: MCP Abilities is a settings-only utility. The whole point is to replace the PHP-filter workaround with a UI.

= Does Inhale: MCP Abilities work with the WordPress AI plugin? =

Inhale: MCP Abilities works alongside the WordPress AI plugin without conflicts. The WordPress AI plugin handles AI-powered editor features inside wp-admin. The Inhale: MCP Abilities plugin handles which abilities are exposed to external MCP clients via the MCP Adapter.

= Is the Inhale: MCP Abilities plugin safe to use on production sites? =

The Inhale: MCP Abilities plugin is conservative by default: no abilities are exposed until you explicitly check them, and destructive abilities require additional confirmation. Inhale: MCP Abilities doesn't change how WordPress abilities work; it only controls their visibility to the default MCP server. Each ability still runs its own permission checks before execution.

= What's the relationship between Inhale: MCP Abilities and Respira? =

Inhale: MCP Abilities is a free utility built and maintained by Respira. Respira's main product is Respira for WordPress, a safety layer for AI-driven edits across 12 page builders. The two products are separate. You can use the Inhale: MCP Abilities plugin without ever using Respira for WordPress.

= Will write operations work through MCP? =

Yes, if you inhale abilities that perform writes. Whether a particular ability performs writes is determined by the plugin that registered the ability, not by Inhale: MCP Abilities. The Inhale: MCP Abilities plugin surfaces annotation metadata (destructive, idempotent) where the registering plugin has provided it, so you can make informed decisions.

== Screenshots ==

1. The Inhale: MCP Abilities settings page in light mode: page header with the "by respira.press" subtitle, sources card listing every plugin that registers abilities, filter tabs (All, Inhaled, Read-only, Destructive, Unannotated), search, and the wp-admin native abilities list table with iOS-style toggle switches in the Status column.
2. The abilities list table close-up: row checkboxes for bulk selection, toggles per row (green when inhaled, grey when not), Source column showing the registering plugin, descriptions sourced from each ability's declared meta, and annotation badges (Read-only, Destructive, Idempotent).
3. The Connection section showing the default MCP server endpoint with copy-to-clipboard, expandable client configuration guides for WP-CLI STDIO and HTTP transports with application passwords, and the About section with the Anthropic trademark disclaimer.
4. Dark mode view: the entire wp-admin content column paints dark together with the Inhale page, the wordmark and subtitle render with brand-spec emerald (#86efac) typography, and the abilities table inherits the dark surface tokens.

== Changelog ==

= 0.3.1 =
* Add the four wp.org Plugin Directory screenshots, captured at 1600x1000 from a live v0.3.0 install (Studio, WordPress 7.0-RC4, 155 registered abilities across Respira, Respira WooCommerce and WordPress core): the settings page hero, the abilities list close-up showing toggle switches and annotation badges, the Connection section with endpoint + transport guides, and the dark-mode view. Screenshot descriptions in readme.txt refreshed to match.

= 0.3.0 =
* Plugin Directory submission release. Full pass on the codebase to meet WordPress.org plugin review guidelines: every PHP file has an ABSPATH guard, every output is escaped through `esc_attr` / `esc_html` / `esc_url`, every input is sanitized and capability-checked, every state-changing request is nonce-verified, and translatable strings carry a text domain matching the plugin slug.
* Settings page chrome: page header restructure with "by respira.press" as a Baskervville italic subtitle and a small version pill in the right-side toolbar. Both adapt per theme via `--accent-text` and `--accent-border` tokens so contrast is AA on light and dark surfaces.
* Theme toggle: layout shift between dark and light is gone. Background, padding and margin moved to the base `.inhale-wrap` selector and the dark variant only redefines color tokens. A body class (`inhale-theme-dark`) mirrors the data-theme attribute so the WP admin content column, sidebar and footer all paint dark together with no "white band" anywhere on the page.
* Notifications scoping: every admin notice queued by other plugins or the active theme is suppressed on the Inhale settings page (license nags, plugin-install banners, trial reminders, etc.). Inhale's own success / warning notices render inline and survive the cleanup.
* Uninstall hardening: `uninstall.php` now removes every option this plugin has ever written: the canonical `mcp_adapter_public_abilities` key (v0.2.0+), the legacy `inhale_mcp_abilities_public_abilities` key (v0.1.x), and the `inhale_option_migrated_v020` migration flag. Multisite installations sweep every blog in `get_sites()` so no orphaned wp_options rows remain.
* Tested with WordPress 7.0-RC4 and PHP 8.4 on Studio. Confirmed no remote calls, no tracking, no obfuscated code, no external dependencies. License is GPL-2.0-or-later with the full GPL text included in `LICENSE`.

= 0.2.3 =
* Eliminate the dark/light theme layout shift. Background, padding and margin now apply to the base `.inhale-wrap` instead of only the dark variant, so toggling theme no longer pushes the title up or down.
* Paint `#wpbody-content` dark too when the dark theme is active, via a body class (`inhale-theme-dark`) the JS adds in lockstep with the `data-theme` attribute. Closes the "white bar at the top" gap between the WP admin bar and the Inhale page in dark mode.
* Restructure the page header: "by respira.press" is now a subtitle directly under the H1 in 14px Baskervville italic emerald, aligned with the title block next to the dot-grid logo.
* Move the version pill from the H1 to the right-side toolbar, sized down to 9.5px monospace lowercase. Sits between the Documentation link and the theme toggle.

= 0.2.2 =
* Suppress every admin notice queued by other plugins or the active theme on the Inhale settings page. Inhale's own notices (rendered inline via render_notice()) survive, every other one (license warnings, plugin-install nags, update banners, etc.) is dropped on this screen only. Implemented via `remove_all_actions( 'admin_notices' / 'all_admin_notices' / 'user_admin_notices' / 'network_admin_notices' )` on the `current_screen` hook, scoped to `settings_page_inhale-mcp-abilities`.
* Add "by respira.press" attribution after the page title, rendered in Baskervville italic emerald (#86efac) per the canonical respira.press/brand spec, with system serif fallbacks so no external font fetch is required.
* Add a small pill next to the attribution showing the current plugin version (`v0.2.2`). Pill uses the emerald accent palette and the mono font, sized 11px, focus-visible underline on the linked attribution.

= 0.2.1 =
* Replace the Status column text pill ("Inhaled" / em-dash) with an iOS-style toggle switch. Green when the ability is inhaled, off when not. Clicking the toggle commits the change immediately, same single-row flow as the existing row-hover quick action. Managed rows (mcp-adapter namespace) render a disabled toggle. Destructive abilities still trigger the confirmation dialog before flipping on.
* No behavioral change on saved data; the option key remains `mcp_adapter_public_abilities` from v0.2.0.

= 0.2.0 =
* Rename the option key from `inhale_mcp_abilities_public_abilities` to the canonical `mcp_adapter_public_abilities` so Inhale shares storage with the settings UI proposed upstream in WordPress/mcp-adapter PR #184. A one-shot migration on plugin upgrade preserves all v0.1.x selections, deletes the legacy key, and sets a `inhale_option_migrated_v020` flag so it runs once.
* No UI or behavior changes. Sites with no prior selections are unaffected.

= 0.1.1 =
* Hardening pass: the permission-denied path in the settings page render now passes HTTP response code 403 and a back link to `wp_die()`, so access logs and automated clients see an authorization failure instead of a generic error.
* Normalize row-class escaping in the abilities table: always render the `<tr>` class attribute through `esc_attr()` instead of conditionally injecting the attribute fragment. No behavioral change, conforms more strictly to the WordPress Plugin Check `WordPress.Security.EscapeOutput` rule.
* Mirrors the equivalent review feedback addressed upstream on WordPress/mcp-adapter PR #184.

= 0.1.0 =
* Initial release.
* Settings page at Settings &gt; Inhale: MCP Abilities, registered with `manage_options` capability.
* Discovers every ability registered via the WordPress Abilities API (`wp_get_abilities()`) and lists them in a wp-admin native list table.
* Standard wp-admin selection + bulk-action UX: row checkboxes are selection, the Bulk Actions dropdown plus Apply commits Inhale or Exhale immediately.
* Row-hover quick actions for single-ability inhale or exhale.
* Annotation badges on each ability (read-only, destructive, idempotent) sourced from the ability's declared meta; falls back to heuristic inference from the ability name when the registering plugin didn't tag it, with a dashed border and asterisk to mark inferred annotations.
* Filter views (All, Inhaled, Read-only, Destructive, Unannotated) and a search box that matches across name, source and description.
* Multi-select source filter on the Source column to narrow by the registering plugin or theme.
* Sortable columns (Ability, Source, Description, Status).
* Client-side pagination: 20 / 50 / 100 / All items per page, with wp-admin-native page navigation chrome.
* Sources summary card above the table listing every plugin or theme that registers abilities, with the count per source and a deep-link to that plugin's wp-admin home.
* Destructive ability confirmation: one consolidated dialog when a bulk Inhale would expose destructive abilities; one dialog per single-row Inhale link.
* Annotation legend section under the table explaining what each annotation means, including how inferred annotations differ from declared ones.
* Connection section showing the default MCP server endpoint with copy-to-clipboard, and expandable connection guides for WP-CLI STDIO transport and HTTP transport with application passwords.
* About section with the MCP / Anthropic trademark disclaimer (this plugin is third-party, not affiliated with Anthropic).
* Light and dark mode support, persisted per browser in localStorage and respecting the wp-admin color scheme on first load.
* WCAG AA contrast in both modes; full keyboard accessibility.
* Filter (`wp_register_ability_args` at priority 10) is the only writer to ability meta; existing meta on opted-in abilities is preserved.
* Adapter-managed abilities (`mcp-adapter/*` namespace) are surfaced as read-only "Managed" rows and skipped by the filter.

== Upgrade Notice ==

= 0.3.1 =
Adds the four wp.org Plugin Directory screenshots; no runtime changes.

= 0.3.0 =
WordPress.org Plugin Directory submission release. Full codebase pass against the plugin review guidelines, page header restructure, dark/light theme parity, light-mode contrast fix, foreign-notice suppression on the Inhale page, and hardened uninstall (single-site and multisite). Safe to upgrade.

= 0.2.0 =
Option key renamed to the canonical `mcp_adapter_public_abilities` shared with WordPress/mcp-adapter PR #184. A one-shot migration runs on plugin upgrade so v0.1.x selections are preserved automatically. Safe to upgrade.

= 0.1.1 =
Security hardening pass on the settings page render path. No new features. Safe to upgrade.

= 0.1.0 =
Initial release.
