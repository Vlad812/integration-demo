<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

final readonly class Currency
{
    private function __construct(
        private string $value,
    ) {
    }

    public static function usd(): self
    {
        return new self('USD');
    }

    public static function fromString(string $value): self
    {
        $value = strtoupper(trim($value));

        if (!preg_match('/^[A-Z]{3}$/', $value)) {
            throw InvalidValueException::forField('currency', 'must be a 3-letter ISO code');
        }

        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }
}
