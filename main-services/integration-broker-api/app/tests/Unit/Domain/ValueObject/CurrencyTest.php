<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Currency;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testUsdFactory(): void
    {
        self::assertSame('USD', Currency::usd()->toString());
    }

    public function testFromStringNormalizes(): void
    {
        self::assertSame('EUR', Currency::fromString(' eur ')->toString());
    }

    public function testFromStringRejectsInvalid(): void
    {
        $this->expectException(InvalidValueException::class);
        Currency::fromString('US');
    }
}
