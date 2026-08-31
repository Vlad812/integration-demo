<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

final class DuplicateIdempotencyKeyException extends DomainException
{
    public static function forKey(string $idempotencyKey): self
    {
        return new self(sprintf('Order with idempotency key "%s" already exists.', $idempotencyKey));
    }
}
