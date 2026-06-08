<?php
declare(strict_types=1);

return [
    'slug'   => 'microsoft',
    'label'  => 'Continue with Microsoft',
    'engine' => 'oidc',
    'issuer' => 'https://login.microsoftonline.com/common/v2.0',
    'scopes' => ['openid', 'email', 'profile'],
];