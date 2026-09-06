<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Shared;

use App\Application\Shared\MoneyStringToCents;
use App\Domain\Exception\InvalidValueException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class MoneyStringToCentsTest extends TestCase
{
    #[DataProvider('validProvider')]
    public function testParse(string $input, int $expected): void
    {
        self::assertSame($expected, MoneyStringToCents::parse($input));
    }

    /** @return iterable<string, array{string, int}> */
    public static function validProvider(): iterable
    {
        yield 'whole' => ['12', 1200];
        yield 'one decimal' => ['12.3', 1230];
        yield 'two decimals' => ['12.34', 1234];
        yield 'trimmed' => [' 1.5 ', 150];
        yield 'zero fraction' => ['0.05', 5];
    }

    #[DataProvider('invalidProvider')]
    public function testParseRejectsInvalid(string $input): void
    {
        $this->expectException(InvalidValueException::class);
        MoneyStringToCents::parse($input);
    }

    /** @return iterable<string, array{string}> */
    public static function invalidProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'three decimals' => ['12.345'];
        yield 'negative' => ['-1.00'];
        yield 'letters' => ['abc'];
    }
}
