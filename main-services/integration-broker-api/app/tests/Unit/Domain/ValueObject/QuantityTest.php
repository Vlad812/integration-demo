<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuantityTest extends TestCase
{
    public function testFromIntAcceptsPositiveValue(): void
    {
        self::assertSame(10, Quantity::fromInt(10)->toInt());
    }

    #[DataProvider('invalidValuesProvider')]
    public function testFromIntRejectsNonPositive(int $value): void
    {
        $this->expectException(InvalidValueException::class);
        Quantity::fromInt($value);
    }

    /** @return iterable<string, array{int}> */
    public static function invalidValuesProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }
}
