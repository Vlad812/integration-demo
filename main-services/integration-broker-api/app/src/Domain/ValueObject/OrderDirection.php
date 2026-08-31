<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;

enum OrderDirection: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';

    public static function fromString(string $value): self
    {
        $normalized = strtoupper(trim($value));

        return match ($normalized) {
            self::Buy->value => self::Buy,
            self::Sell->value => self::Sell,
            default => throw InvalidValueException::forField('direction', 'must be BUY or SELL'),
        };
    }
}
