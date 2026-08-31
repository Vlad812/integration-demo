<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

final readonly class Ticker
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function fromString(string $value): self
    {
        $value = strtoupper(trim($value));

        if ($value === '') {
            throw InvalidValueException::forField('ticker', 'must not be empty');
        }

        if (strlen($value) > 32) {
            throw InvalidValueException::forField('ticker', 'must not exceed 32 characters');
        }

        if (!preg_match('/^[A-Z0-9._-]+$/', $value)) {
            throw InvalidValueException::forField('ticker', 'contains invalid characters');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
