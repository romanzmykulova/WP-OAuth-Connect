<?php
declare(strict_types=1);

use WpOAuthConnect\Provider\Adapter\GithubProfileAdapter;

return [
    'slug'            => 'github',
    'label'           => 'Continue with GitHub',
    'engine'          => 'oauth2',
    'authorize_url'   => 'https://github.com/login/oauth/authorize',
    'token_url'       => 'https://github.com/login/oauth/access_token',
    'scopes'          => ['read:user', 'user:email'],
    'profile_adapter' => GithubProfileAdapter::class,
];