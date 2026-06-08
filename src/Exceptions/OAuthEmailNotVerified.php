<?php
declare(strict_types=1);

/**
 * Thrown when a provider returns an unverified email on a verified-only flow.
 */

namespace WpOAuthConnect\Exceptions;

final class OAuthEmailNotVerified extends \RuntimeException
{
}