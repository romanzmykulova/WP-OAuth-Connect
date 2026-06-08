<?php
declare(strict_types=1);

/**
 * Thrown when an OAuth state token is past its TTL.
 */

namespace WpOAuthConnect\Exceptions;

final class OAuthStateExpired extends OAuthStateInvalid
{
}