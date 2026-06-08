<?php
declare(strict_types=1);

/**
 * Loads presets + filtered definitions and builds runtime Provider engines.
 */

namespace WpOAuthConnect\Provider;

use WpOAuthConnect\Exceptions\ProviderNotFound;
use WpOAuthConnect\Options\Settings;
use WpOAuthConnect\Support\HttpClient;
use WpOAuthConnect\Support\WpHttpClient;

final class ProviderRegistry
{
    /** @var array<string, Provider> */
    private array $providers = [];

    /** @var array<string, ProviderDefinition> */
    private array $definitions = [];

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    public static function fromPluginDir(string $pluginDir, ?HttpClient $http = null): self
    {
        $registry = new self($http ?? new WpHttpClient());
        $registry->loadPresets($pluginDir);
        $registry->loadConfigConstantDefinitions();
        $registry->applyDefinitionsFilter();
        $registry->buildProvidersFromDefinitions();
        \do_action('woc_oauth_init', $registry);
        $registry->applyProvidersFilter();
        return $registry;
    }

    /**
     * @return list<Provider>
     */
    public function all(): array
    {
        return array_values($this->providers);
    }

    /**
     * @return list<ProviderDefinition>
     */
    public function definitions(): array
    {
        return array_values($this->definitions);
    }

    public function get(string $slug): Provider
    {
        if (!isset($this->providers[$slug])) {
            throw new ProviderNotFound('Unknown OAuth provider: ' . $slug);
        }
        return $this->providers[$slug];
    }

    public function has(string $slug): bool
    {
        return isset($this->providers[$slug]);
    }

    public function enabled(string $slug): bool
    {
        return $this->has($slug) && Settings::isProviderOperational($slug);
    }

    public function register(Provider $provider): void
    {
        $slug = $provider->slug();
        if (isset($this->providers[$slug])) {
            throw new \InvalidArgumentException('Duplicate provider slug: ' . $slug);
        }
        $this->providers[$slug] = $provider;
    }

    public function registerDefinition(ProviderDefinition $definition): void
    {
        if (isset($this->definitions[$definition->slug])) {
            throw new \InvalidArgumentException('Duplicate provider definition: ' . $definition->slug);
        }
        $this->definitions[$definition->slug] = $definition;
        $this->providers[$definition->slug] = $this->buildProvider($definition);
    }

    private function loadPresets(string $pluginDir): void
    {
        $pattern = rtrim($pluginDir, '/') . '/src/config/presets/*.php';
        foreach (glob($pattern) ?: [] as $file) {
            /** @var array<string, mixed> $raw */
            $raw = require $file;
            $this->addDefinition(ProviderDefinition::fromArray($raw));
        }
    }

    private function loadConfigConstantDefinitions(): void
    {
        if (!defined('WOC_OAUTH_PROVIDERS')) {
            return;
        }

        $raw = constant('WOC_OAUTH_PROVIDERS');
        if (!is_string($raw)) {
            return;
        }

        $decoded = @unserialize($raw, ['allowed_classes' => false]);
        if (!is_array($decoded)) {
            return;
        }

        foreach ($decoded as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $this->addDefinition(ProviderDefinition::fromArray($entry));
        }
    }

    private function applyDefinitionsFilter(): void
    {
        $rawDefinitions = array_values($this->definitions);
        /** @var list<array<string, mixed>> $filtered */
        $filtered = \apply_filters('woc_oauth_provider_definitions', array_map(
            static fn (ProviderDefinition $definition): array => [
                'slug'             => $definition->slug,
                'label'            => $definition->label,
                'engine'           => $definition->engine,
                'scopes'           => $definition->scopes,
                'issuer'           => $definition->issuer,
                'authorize_url'    => $definition->authorizeUrl,
                'token_url'        => $definition->tokenUrl,
                'profile_url'      => $definition->profileUrl,
                'claim_map'        => $definition->claimMap,
                'profile_adapter'  => $definition->profileAdapter,
                'token_auth_method'=> $definition->tokenAuthMethod,
                'enabled_by_default' => $definition->enabledByDefault,
            ],
            $rawDefinitions,
        ));

        $this->definitions = [];
        $this->providers   = [];

        foreach ($filtered as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $this->addDefinition(ProviderDefinition::fromArray($entry));
        }
    }

    private function buildProvidersFromDefinitions(): void
    {
        foreach ($this->definitions as $definition) {
            if (!isset($this->providers[$definition->slug])) {
                $this->providers[$definition->slug] = $this->buildProvider($definition);
            }
        }
    }

    private function applyProvidersFilter(): void
    {
        /** @var list<Provider> $extra */
        $extra = \apply_filters('woc_oauth_providers', []);
        foreach ($extra as $provider) {
            if (!$provider instanceof Provider) {
                continue;
            }
            $this->register($provider);
        }
    }

    private function addDefinition(ProviderDefinition $definition): void
    {
        if (isset($this->definitions[$definition->slug])) {
            throw new \InvalidArgumentException('Duplicate provider definition: ' . $definition->slug);
        }
        $this->definitions[$definition->slug] = $definition;
    }

    private function buildProvider(ProviderDefinition $definition): Provider
    {
        $credentials = $this->resolveCredentials($definition->slug);

        return match ($definition->engine) {
            'oidc'   => new OidcProvider($definition, $credentials['client_id'], $credentials['client_secret'], $this->http),
            'oauth2' => new OAuth2Provider($definition, $credentials['client_id'], $credentials['client_secret'], $this->http),
            default  => throw new \InvalidArgumentException('Unsupported engine: ' . $definition->engine),
        };
    }

    /**
     * @return array{client_id: string, client_secret: string}
     */
    private function resolveCredentials(string $slug): array
    {
        $defaults = [
            'client_id'     => Settings::providerClientId($slug),
            'client_secret' => Settings::providerClientSecret($slug),
        ];

        /** @var array{client_id?: string, client_secret?: string} $filtered */
        $filtered = \apply_filters('woc_oauth_provider_credentials', $defaults, $slug);

        return [
            'client_id'     => (string) ($filtered['client_id'] ?? ''),
            'client_secret' => (string) ($filtered['client_secret'] ?? ''),
        ];
    }
}