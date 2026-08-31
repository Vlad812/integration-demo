<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

final readonly class ClientId
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = trim($value);

        if ($value === '') {
            throw InvalidValueException::forField('client_id', 'must not be empty');
        }

        if (strlen($value) > 64) {
            throw InvalidValueException::forField('client_id', 'must not exceed 64 characters');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
