<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

final readonly class Quantity
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function fromInt(int $value): self
    {
        if ($value <= 0) {
            throw InvalidValueException::forField('quantity', 'must be greater than zero');
        }

        return new self($value);
    }

    public function toInt(): int
    {
        return $this->value;
    }
}
