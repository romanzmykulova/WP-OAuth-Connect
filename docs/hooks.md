# wp-oauth-connect — hook contract

Stable public API for companion plugins (`remotejobs-core`, corporate app, etc.).

## Public functions

```php
oauth_providers(): array;                              // enabled metadata per provider
oauth_start_url(string $provider, array $context = []): string;
oauth_provider_enabled(string $provider): bool;
oauth_native_login_enabled(): bool;
```

`$context` keys on `oauth_start_url()`: `invite`, `next`.

## Routes

| Path | Purpose |
| --- | --- |
| `/oauth/{slug}/start` | Redirect to provider authorize URL |
| `/oauth/{slug}/callback` | Verify state, exchange code, run decision tree |
| `/oauth/bind` | BIND-PROMPT after email collision |

## Shipped presets

| Slug | Engine | Notes |
| --- | --- | --- |
| `linkedin` | OIDC | Issuer `https://www.linkedin.com/oauth` |
| `google` | OIDC | Issuer `https://accounts.google.com` |
| `github` | OAuth2 | Uses `GithubProfileAdapter` for `/user/emails` |
| `microsoft` | OIDC | Disabled until creds + enable flag |
| `custom` | OIDC | Admin UI — label, icon, issuer, creds (Settings → OAuth Connect) |

## Custom provider (admin UI)

One OIDC slot with slug `custom`. Configure button label, icon text or inline SVG,
issuer URL, scopes, and client credentials on **Settings → OAuth Connect**. Include
`custom` in the login button order field (e.g. `linkedin,custom,google`).

## Add a custom OIDC provider (no new PHP)

```php
add_filter('woc_oauth_provider_definitions', function (array $defs): array {
    $defs[] = [
        'slug'   => 'okta-acme',
        'label'  => 'Sign in with Acme SSO',
        'engine' => 'oidc',
        'issuer' => 'https://acme.okta.com/oauth2/default',
        'scopes' => ['openid', 'email', 'profile'],
    ];
    return $defs;
});
```

Credentials: `OAUTH_OKTA_ACME_CLIENT_ID` / `OAUTH_OKTA_ACME_CLIENT_SECRET` in `wp-config.php`.
Enable: `woc_oauth_okta-acme_enabled` option (admin → Settings → OAuth Connect).

## Filters

| Hook | Purpose |
| --- | --- |
| `woc_oauth_provider_definitions` | Add providers by config array |
| `woc_oauth_providers` | Register custom `Provider` class (rare) |
| `woc_oauth_provider_credentials` | Override cred source per slug |
| `woc_oauth_provider_enabled` | Override operational predicate |
| `woc_oauth_provider_button` | Tweak login button descriptor |
| `woc_oauth_state_payload` | Add signed context (`invite_token`, `required_email`, …) |
| `woc_oauth_allow_registration` | **Default `false`** — companion must opt in |
| `woc_oauth_create_user` | Companion creates user; `null` = minimal WP user |
| `woc_oauth_redirect_url` | Post-auth redirect |
| `woc_oauth_native_login_enabled` | Native login toggle |
| `woc_oauth_render_login_form` | **Default `true`** — render OAuth buttons on the native wp-login.php form. Return false when a companion renders its own login UI |
| `woc_oauth_login_buttons` | Login UI button list |
| `woc_oauth_bind_prompt_message` | BIND-PROMPT copy |
| `woc_oauth_reject_message` | Callback rejection copy |

## Actions

| Hook | Purpose |
| --- | --- |
| `woc_oauth_init` | Register custom `Provider` on `ProviderRegistry` |
| `woc_oauth_authenticated` | Existing linked user logged in |
| `woc_oauth_user_registered` | New user created via OAuth |
| `woc_oauth_identity_bound` | BIND-PROMPT completed |
| `woc_oauth_registration_rejected` | Registration denied (audit/metrics) |

## RemoteJobs.team integration

See workspace `docs/oauth-plan.md` — adapter hooks:

- `woc_oauth_state_payload` → add `invite_token`
- `woc_oauth_allow_registration` → valid invite only
- `woc_oauth_create_user` → `SignupService::createFromOAuth()`
- `woc_oauth_redirect_url` → `/directory` or `?next=`