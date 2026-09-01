<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Domain\Exception\InvalidValueException;

final class MoneyStringToCents
{
    public static function parse(string $amount): int
    {
        $amount = trim($amount);

        if ($amount === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $amount)) {
            throw InvalidValueException::forField('amount', sprintf('invalid money string "%s"', $amount));
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');

        return ((int) $whole * 100) + (int) $fraction;
    }
}
