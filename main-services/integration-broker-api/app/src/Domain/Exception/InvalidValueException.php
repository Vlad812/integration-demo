<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

final class InvalidValueException extends DomainException
{
    public static function forField(string $field, string $reason): self
    {
        return new self(sprintf('Invalid value for "%s": %s.', $field, $reason));
    }
}
