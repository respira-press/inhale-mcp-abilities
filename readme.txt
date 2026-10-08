=== Inhale: MCP Abilities Manager by Respira ===
Contributors: urbankidro
Tags: mcp, mcp server, claude, chatgpt, abilities
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

See every ability your plugins give AI agents over MCP, and choose which ones Claude, ChatGPT and other AI clients can use. No code.

== Description ==

https://www.youtube.com/watch?v=9xkrJ2rJ8DM

Inhale: MCP Abilities Manager shows you, on one screen, every action your WordPress site offers to AI agents through the Model Context Protocol (MCP), and lets you decide which of them an AI client may use.

**Why this matters now.** Since WordPress 6.9, plugins register "abilities": small, named actions such as "get site info", "create a post" or "update a product". The WordPress MCP Adapter turns those abilities into tools that Claude, ChatGPT, Cursor and other AI clients can call. Since adapter 0.6 and WordPress 7.1, a plugin that marks its ability as public makes it reachable by AI clients without the site owner choosing. And you may already run the adapter without knowing it: several popular SEO, page builder, form and store plugins load their own copy.

Inhale puts that decision back with you. Nothing changes until you choose.

= What Inhale does =

* Lists every registered ability on the site, from every plugin and theme, with what it does and which plugin registered it.
* Lets you switch each one on or off for MCP with a checkbox, or in bulk.
* Shows the safety hints each ability carries: read-only, destructive, idempotent. Filter to read-only abilities in one click. Turning on a destructive ability asks you to confirm.
* Shows your MCP setup at a glance: whether an MCP Adapter is running, its version and which plugin loads it, your endpoint URL, and whether Application Passwords work on this site.
* Gives copy-paste connection steps for Claude Desktop, Claude Code, Cursor, VS Code and WP-CLI.
* Keeps your choice when a plugin author marks an ability public. On WordPress 7.1, every ability gets an explicit decision from you, so "not selected" really means not exposed.

= What Inhale does not do =

* It does not run an MCP server or handle sign-in. The WordPress MCP Adapter does that, whichever plugin loads it.
* It does not register abilities of its own or change what an ability does. Every ability still runs its own permission checks for the signed-in user.
* It does not phone home, collect telemetry or load anything from outside your site. Nothing is sent anywhere unless you connect a free Respira account for the site check below.

= Free with a Respira account: a weekly vulnerability check =

Connect a free respira.press account from the Inhale screen, and Inhale checks WordPress core, every installed plugin and every installed theme against more than 40,000 known vulnerabilities from the Wordfence Intelligence database, once a week and whenever you click Check now.

* The Inhale screen shows which plugins or themes have known vulnerabilities, how severe the worst one is, and the version that fixes them.
* Your free Respira dashboard lists every record with its CVE and a link to Wordfence, for every site you connect.
* The same free account includes an accessibility scan of any page on the site.

No card and no trial. Inhale works the same whether you connect or not, and Disconnect removes the connection on both ends. What is sent, and when, is listed under External services below.

= Requirements =

* WordPress 6.8 or later. The Abilities API is in core from 6.9; on 6.8 it needs the Abilities API plugin.
* PHP 7.4 or later.
* A WordPress MCP Adapter, for AI clients to connect: the [MCP Adapter plugin](https://wordpress.org/plugins/mcp-adapter/) or a copy loaded by another plugin. Inhale shows which one is running. You can make your choices before an adapter is installed; they apply once one runs.

= Browse the abilities directory =

Respira keeps a public directory of WordPress plugins that register abilities. Browse it at [respira.press/abilities](https://www.respira.press/abilities?utm_source=inhale&utm_medium=wp-org&utm_campaign=readme-abilities-directory) to see what a plugin exposes before you install it.

= About MCP =

Model Context Protocol (MCP) is an open specification originally developed by Anthropic. Inhale is a third-party plugin and is not affiliated with, endorsed by, or sponsored by Anthropic or OpenAI.

= About Respira =

Inhale is built and maintained by Respira. Respira's main product, Respira for WordPress, lets AI apps edit WordPress sites in the page builder each site already uses, with a snapshot before every write and one-click rollback, and signs in from claude.ai and ChatGPT in the browser. Inhale is free and works on its own. Learn more at [respira.press/inhale](https://respira.press/inhale?utm_source=inhale&utm_medium=wp-org&utm_campaign=readme-description).

== Installation ==

1. Install and activate Inhale from Plugins > Add New Plugin.
2. Go to Settings > Inhale: MCP Abilities. The "Your MCP setup" card shows whether an MCP Adapter is running. If none is, use the "Get MCP Adapter" link to install the MCP Adapter plugin.
3. Tick the abilities you want AI clients to use, or filter to read-only abilities and select them all. Apply.
4. Connect your AI client with the steps under Connection on the same page. The endpoint shown there is already correct for your site, including sites in a subdirectory and sites with plain permalinks.

== Frequently Asked Questions ==

= How do I connect Claude to my WordPress site? =

Create an Application Password under Users > Profile, then use the Connection steps on the Inhale settings page. Claude Desktop uses a small helper started with npx; Claude Code connects directly with a one-line command. Both are shown with your site's real endpoint filled in.

= Can claude.ai or ChatGPT in the browser connect? =

Not with an Application Password. The web versions of Claude and ChatGPT sign in with OAuth, and the MCP Adapter does not provide OAuth on its own. Desktop and command-line clients work. Respira for WordPress adds an OAuth sign-in if you need the browser apps.

= Which abilities are exposed on my site right now? =

Open Settings > Inhale: MCP Abilities. The status card shows how many abilities are exposed out of how many are registered, and the list shows each one with its source plugin. Use the "Inhaled" view to see only the exposed ones.

= I installed an SEO, page builder or store plugin. Is an MCP server running? =

Possibly. Several popular plugins load their own copy of the MCP Adapter. The status card shows whether an adapter is running and which plugin loads it, so you know before an AI client connects.

= Do I need to write any code? =

No. Inhale replaces the PHP filters you would otherwise write with checkboxes.

= Is it safe to use on production sites? =

Inhale only controls visibility. It never grants a permission the signed-in user does not have, and every ability runs its own checks before it does anything. Turning on a destructive ability asks you to confirm. For production, connect AI clients as a dedicated user with the lowest role that can do the job.

= Does Inhale work with the WordPress AI plugin? =

Yes. The WordPress AI plugin adds AI features inside wp-admin; Inhale controls which abilities external MCP clients can reach. They do not overlap.

= What is the relationship between Inhale and Respira for WordPress? =

Respira builds and maintains Inhale for free. Respira for WordPress includes the same controls, so you do not need both; Inhale steps aside automatically when Respira is active.

= Can Inhale check my site for vulnerable plugins? =

Yes, with a free Respira account. Click Connect on the Inhale screen and approve on respira.press. Inhale then checks WordPress, your plugins and your themes against the Wordfence Intelligence vulnerability database every week and shows what to update. Nothing is sent before you connect.

== External services ==

Inhale connects to one external service, and only after an administrator chooses to: Respira (respira.press), for the free site check.

* When: when an administrator clicks "Connect a free account" and approves on respira.press, then once a week, and whenever an administrator clicks "Check now". "Disconnect" sends one last request that deletes the connection.
* What is sent: the site address, the WordPress version, and the folder name, name and version of each installed plugin and theme, with the site's connection token. No content, no users, no personal data from the site.
* What it is for: matching those versions against known vulnerabilities and showing the result on the Inhale screen and in your Respira dashboard.
* Terms: https://www.respira.press/terms
* Privacy policy: https://www.respira.press/privacy

Vulnerability data comes from the Wordfence Intelligence database, copyright Defiant Inc., used under its licence; each record links to Wordfence.

Before you connect, Inhale makes no external requests. Links on the settings page open respira.press or wordpress.org only when you click them.

== Screenshots ==

1. Every ability on the site, from every plugin, with what it does, who registered it and its safety hints. Switch each one on or off for MCP.
2. Your MCP setup at a glance, and the free site check: which plugins have known vulnerabilities and the version that fixes them.
3. Working connection steps for Claude Desktop, Claude Code, Cursor, VS Code and WP-CLI.
4. The same screen in dark mode.

== Changelog ==

= 0.7.0 =
* Added: a free site check. Connect a free Respira account and Inhale checks WordPress, your plugins and your themes against 40,000+ known vulnerabilities from Wordfence Intelligence, every week, and shows what to update. Opt in, off until you connect, listed under External services.
* Added: a link to the free accessibility scan that comes with the same account.
* Changed: uninstalling Inhale also removes the site check connection, unless Respira ARC is still installed and uses it.

= 0.6.0 =
* Added: a "Your MCP setup" card at the top of the settings page: whether an MCP Adapter is running, its version and which plugin loads it, the endpoint, whether Application Passwords work on this site, and how many abilities are exposed.
* Changed: Inhale no longer says it needs the MCP Adapter plugin specifically. It works with any copy of the adapter, including the ones other plugins load, and your choices apply as soon as one runs.
* Fixed: the Connection steps showed a config format no client reads and described Application Passwords as "four groups of six" characters. They now show working setups for Claude Desktop, Cursor and VS Code (through @automattic/mcp-wordpress-remote) and for Claude Code (a single command with a one-line Basic header).
* Added: one rating request, a week after you first save a choice, on this page only, with a "No thanks" that is permanent.

= 0.5.1 =
* Fixed: activating Inhale on a site already running Respira for WordPress could end in a fatal error. Inhale now steps aside with a notice when Respira has already loaded it.

= 0.5.0 =
* Changed: WordPress 7.1 compatibility. 7.1 adds a unified meta.public flag for abilities; every ability now carries an explicit decision from you on the MCP channel, so an ability you did not select stays unexposed even if its author marked it public.

= 0.4.5 =
* Added: "Connect with Respira for WordPress" as an option in the Connection section. Links only, no runtime calls.

= Earlier versions =
The full history is on [GitHub](https://github.com/respira-press/inhale-mcp-abilities/commits/main).

== Upgrade Notice ==

= 0.7.0 =
Adds an optional free weekly vulnerability check of your plugins and themes. Nothing is sent unless you connect.

= 0.6.0 =
Adds a status card showing whether an MCP Adapter runs and which plugin loads it, and fixes the connection steps.

= 0.5.1 =
Fixes a fatal error when Inhale is activated on a site already running Respira for WordPress.
