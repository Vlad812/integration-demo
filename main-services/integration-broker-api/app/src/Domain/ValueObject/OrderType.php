<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

enum OrderType: string
{
    case Market = 'MARKET';

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            self::Market->value => self::Market,
            default => throw InvalidValueException::forField('type', 'must be MARKET'),
        };
    }
}
