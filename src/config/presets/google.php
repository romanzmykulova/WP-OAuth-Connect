<?php
declare(strict_types=1);

return [
    'slug'   => 'google',
    'label'  => 'Continue with Google',
    'engine' => 'oidc',
    'issuer' => 'https://accounts.google.com',
    'scopes' => ['openid', 'email', 'profile'],
];