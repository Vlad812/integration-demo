<?php

declare(strict_types=1);

namespace App\Application\Exception;

use InvalidArgumentException;

final class InvalidParameter extends InvalidArgumentException
{
    public static function missing(string $field): self
    {
        return new self(sprintf('Missing required parameter "%s".', $field));
    }

    public static function invalid(string $field, string $reason): self
    {
        return new self(sprintf('Invalid parameter "%s": %s.', $field, $reason));
    }
}
