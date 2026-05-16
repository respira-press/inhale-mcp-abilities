=== Inhale: MCP Abilities ===
Contributors: respira
Tags: mcp, ai, abilities, model context protocol, ai infrastructure
Requires at least: 6.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 0.1.0
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

The Inhale: MCP Abilities plugin is built and maintained by Respira, which ships AI infrastructure for WordPress. The main product is Respira for WordPress, a safety layer that registers 130+ abilities across 12 page builders (Elementor, Bricks, Divi, Beaver Builder, Oxygen, Breakdance and 6 more) with snapshot-before-write protection, render validation and one-click rollback. Free add-ons extend Respira's coverage to WooCommerce, SEO and newsletters. Inhale: MCP Abilities is a free utility offered to the WordPress community. Learn more at https://respira.press.

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

1. The Inhale: MCP Abilities settings page, showing the abilities table with checkboxes and annotation badges.
2. The destructive confirmation flow.
3. The Connection section with MCP server endpoint and client configuration examples.
4. Dark mode view of the settings page.

== Changelog ==

= 0.1.0 =
* Initial release
* Settings page at Settings &gt; Inhale: MCP Abilities
* Per-ability checkboxes to control default MCP server visibility
* Annotation badges (read-only, destructive, idempotent, unannotated) on each ability
* Destructive ability confirmation flow
* Filter views above the abilities table (All, Inhaled, Read-only, Destructive, Unannotated)
* Bulk actions to inhale or exhale multiple abilities at once
* Search input for filtering abilities
* Pagination for sites with many registered abilities
* Connection info showing the default MCP server endpoint with copy-to-clipboard
* Expandable connection guides for WP-CLI STDIO transport and HTTP transport with application passwords
* Light and dark mode support
* Full keyboard accessibility and WCAG AA compliance

== Upgrade Notice ==

= 0.1.0 =
Initial release.
