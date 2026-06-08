<?php
declare(strict_types=1);

/**
 * Thrown when an OAuth state token fails HMAC verification or parsing.
 */

namespace WpOAuthConnect\Exceptions;

class OAuthStateInvalid extends \RuntimeException
{
}