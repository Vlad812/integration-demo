<?php

declare(strict_types=1);

namespace App\Application\Exception;

use RuntimeException;

final class AuthenticationRequiredException extends RuntimeException
{
    public static function missingJwtClient(): self
    {
        return new self('Authentication required: valid JWT with client_id claim expected.');
    }
}
