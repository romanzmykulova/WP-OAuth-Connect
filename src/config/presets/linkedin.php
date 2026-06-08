<?php
declare(strict_types=1);

return [
    'slug'   => 'linkedin',
    'label'  => 'Continue with LinkedIn',
    'engine' => 'oidc',
    'issuer' => 'https://www.linkedin.com/oauth',
    'scopes' => ['openid', 'profile', 'email'],
];