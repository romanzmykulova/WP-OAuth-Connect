<?php
declare(strict_types=1);

/**
 * Strategy hook for non-standard OAuth2 profile fetches.
 */

namespace WpOAuthConnect\Provider\Adapter;

use WpOAuthConnect\OAuthProfile;
use WpOAuthConnect\Provider\ProviderDefinition;
use WpOAuthConnect\Support\HttpClient;

interface ProfileAdapter
{
    public function fetchProfile(
        HttpClient $http,
        string $accessToken,
        ProviderDefinition $definition,
    ): OAuthProfile;
}