=== PostRail MCP ===
Contributors: ryhowww
Tags: mcp, ai, wordpress, postrail, remote-management
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 1.1.2
License: GPLv2 or later

MCP server endpoint for PostRail — enables remote WordPress management.

== Description ==

PostRail MCP is the WordPress-side MCP server for PostRail. It exposes 25 tools via the Model Context Protocol, allowing PostRail to manage content, read files, query databases, run WP-CLI, and more — all through a secure shared secret.

This plugin does not provide direct user access. All authentication and authorization is handled by the PostRail server. The plugin only accepts requests from PostRail using a shared secret.

**Tools available:**

* Site info and PHP diagnostics
* Content CRUD (posts, pages, custom post types)
* Media library browsing
* Option read/write and search
* Taxonomy and term management
* Plugin list, activate, deactivate
* Filesystem read/write/list/rename
* Database queries (SQL with prefix substitution)
* Error log read/clear
* WP-CLI command execution
* Cache purge (auto-detects hosting: WP Engine, Cloudways, Flywheel, LiteSpeed, etc.)

== Installation ==

1. Upload `postrail-mcp` to `/wp-content/plugins/`
2. Activate the plugin
3. Go to Settings > PostRail MCP
4. Enter the shared secret from your PostRail account

== Changelog ==

= 1.1.1 =
* Redesigned settings page — clean card layout with accent color styling
* Secret is masked after saving (shows first 4 characters only)
* Change/Cancel flow for updating existing secret
* Standalone usage note moved to footnote

= 1.1.0 =
* Simplified settings page — single text input for connection secret
* Removed auto-generation of secrets on activation
* Added help text for PostRail users and standalone users
* Health endpoint now returns secret_configured status

= 1.0.0 =
* Initial release — 25 tools, shared secret auth, health check endpoint, auto-updates
