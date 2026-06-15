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

A successful sign-in logs the member in with standard WordPress auth cookies
(wp_set_auth_cookie), so an OAuth session is identical to a wp-login.php
password session — roles, capabilities and is_user_logged_in() all behave the
same. Native password login keeps working; a single native-login toggle lets a
companion go OAuth-only when desired.

Existing accounts are linked safely via the BIND-PROMPT: when a provider email
matches an existing user, the visitor must confirm the account password before
the identities are stitched together (no silent takeover, no duplicate users).

Both styleable surfaces — the provider buttons (oauth-btn / oauth-btn--{slug})
and the bind page (woc_oauth_bind_template) — are fully overridable.

Companion-plugin examples (login buttons, registration policy, connecting
existing accounts, custom providers, and styling) are in README.md; the full
filter/action contract is in docs/hooks.md.

== Installation ==

1. Symlink or copy into wp-content/plugins/wp-oauth-connect/
2. Run composer install in the plugin directory
3. Define per-provider client constants in wp-config.php (OAUTH_STATE_KEY is auto-written to wp-config when possible; otherwise shown on Settings → OAuth Connect)
4. Activate the plugin and enable providers on Settings → OAuth Connect

== Hooks ==

See docs/hooks.md (shipped in v0.1.0).