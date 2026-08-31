<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

final readonly class IdempotencyKey
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        if (!self::isUuid($value)) {
            throw InvalidValueException::forField('idempotency_key', 'must be a valid UUID');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    private static function isUuid(string $value): bool
    {
        return preg_match('/\A[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\z/i', $value) === 1;
    }
}
