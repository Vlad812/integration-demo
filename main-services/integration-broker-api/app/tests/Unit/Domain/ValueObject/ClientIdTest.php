<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\ClientId;
use PHPUnit\Framework\TestCase;

final class ClientIdTest extends TestCase
{
    public function testFromStringTrims(): void
    {
        self::assertSame('client-1', ClientId::fromString('  client-1  ')->toString());
    }

    public function testFromStringRejectsEmpty(): void
    {
        $this->expectException(InvalidValueException::class);
        ClientId::fromString('   ');
    }

    public function testFromStringRejectsTooLong(): void
    {
        $this->expectException(InvalidValueException::class);
        ClientId::fromString(str_repeat('a', 65));
    }
}
