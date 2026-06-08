=== WP OAuth Connect ===
Contributors: remotejobs
Requires at least: 6.4
Requires PHP: 8.3
Stable tag: 0.1.0
License: Proprietary

Generic OAuth transport + identity-linking layer for WordPress.

== Description ==

Thin OAuth plumbing: provider engines, /oauth/{provider}/start|callback routes,
HMAC-signed state, account linking, and a documented hook contract (woc_*).
Registration policy and login UI stay in companion application plugins.

== Installation ==

1. Symlink or copy into wp-content/plugins/wp-oauth-connect/
2. Run composer install in the plugin directory
3. Define per-provider client constants in wp-config.php (OAUTH_STATE_KEY is auto-written to wp-config when possible; otherwise shown on Settings → OAuth Connect)
4. Activate the plugin and enable providers on Settings → OAuth Connect

== Hooks ==

See docs/hooks.md (shipped in v0.1.0).