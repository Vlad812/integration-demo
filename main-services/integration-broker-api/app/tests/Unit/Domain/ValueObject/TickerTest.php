<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\Ticker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TickerTest extends TestCase
{
    #[DataProvider('validProvider')]
    public function testFromStringNormalizesValidTicker(string $input, string $expected): void
    {
        self::assertSame($expected, Ticker::fromString($input)->toString());
    }

    /** @return iterable<string, array{string, string}> */
    public static function validProvider(): iterable
    {
        yield 'uppercase' => ['AAPL', 'AAPL'];
        yield 'trim and upper' => ['  aapl  ', 'AAPL'];
        yield 'with dots and dashes' => ['BRK.B', 'BRK.B'];
        yield 'underscore' => ['FOO_BAR', 'FOO_BAR'];
    }

    #[DataProvider('invalidProvider')]
    public function testFromStringRejectsInvalid(string $input): void
    {
        $this->expectException(InvalidValueException::class);
        Ticker::fromString($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => ['   '];
        yield 'too long' => [str_repeat('A', 33)];
        yield 'invalid chars' => ['AA PL'];
    }
}
