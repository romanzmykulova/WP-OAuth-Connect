<?php
declare(strict_types=1);

/**
 * Declarative provider config — presets and filter-added definitions.
 */

namespace WpOAuthConnect\Provider;

final readonly class ProviderDefinition
{
    /**
     * @param list<string> $scopes
     * @param array<string, string> $claimMap
     */
    public function __construct(
        public string $slug,
        public string $label,
        public string $engine,
        public array $scopes,
        public string $issuer = '',
        public string $authorizeUrl = '',
        public string $tokenUrl = '',
        public string $profileUrl = '',
        public array $claimMap = [],
        public string $profileAdapter = '',
        public string $tokenAuthMethod = 'client_secret_post',
        public bool $enabledByDefault = false,
    ) {
        if (!preg_match('/^[a-z0-9_-]{2,32}$/', $this->slug)) {
            throw new \InvalidArgumentException('Invalid provider slug: ' . $this->slug);
        }
        if (!in_array($this->engine, ['oidc', 'oauth2'], true)) {
            throw new \InvalidArgumentException('Engine must be oidc or oauth2: ' . $this->engine);
        }
    }

    /**
     * @param array<string, mixed> $raw
     */
    public static function fromArray(array $raw): self
    {
        $scopes = $raw['scopes'] ?? [];
        if (!is_array($scopes)) {
            $scopes = [];
        }

        $claimMap = $raw['claim_map'] ?? [];
        if (!is_array($claimMap)) {
            $claimMap = [];
        }

        /** @var list<string> $scopesList */
        $scopesList = array_values(array_map('strval', $scopes));

        /** @var array<string, string> $claimMapTyped */
        $claimMapTyped = [];
        foreach ($claimMap as $key => $value) {
            $claimMapTyped[(string) $key] = (string) $value;
        }

        return new self(
            slug: (string) ($raw['slug'] ?? ''),
            label: (string) ($raw['label'] ?? ''),
            engine: (string) ($raw['engine'] ?? ''),
            scopes: $scopesList,
            issuer: (string) ($raw['issuer'] ?? ''),
            authorizeUrl: (string) ($raw['authorize_url'] ?? ''),
            tokenUrl: (string) ($raw['token_url'] ?? ''),
            profileUrl: (string) ($raw['profile_url'] ?? ''),
            claimMap: $claimMapTyped,
            profileAdapter: (string) ($raw['profile_adapter'] ?? ''),
            tokenAuthMethod: (string) ($raw['token_auth_method'] ?? 'client_secret_post'),
            enabledByDefault: (bool) ($raw['enabled_by_default'] ?? false),
        );
    }
}