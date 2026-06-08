<?php
declare(strict_types=1);

/**
 * Thrown when a provider slug is unknown to the registry.
 */

namespace WpOAuthConnect\Exceptions;

final class ProviderNotFound extends \RuntimeException
{
}