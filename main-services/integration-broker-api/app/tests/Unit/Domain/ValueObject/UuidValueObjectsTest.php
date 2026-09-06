<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueException;
use App\Domain\ValueObject\IdempotencyKey;
use App\Domain\ValueObject\OrderId;
use PHPUnit\Framework\TestCase;

final class UuidValueObjectsTest extends TestCase
{
    private const string UUID = '550e8400-e29b-41d4-a716-446655440000';

    public function testIdempotencyKeyAcceptsUuid(): void
    {
        self::assertSame(self::UUID, IdempotencyKey::fromString(self::UUID)->toString());
    }

    public function testIdempotencyKeyRejectsInvalid(): void
    {
        $this->expectException(InvalidValueException::class);
        IdempotencyKey::fromString('not-a-uuid');
    }

    public function testOrderIdAcceptsUuid(): void
    {
        self::assertSame(self::UUID, OrderId::fromString(self::UUID)->toString());
    }

    public function testOrderIdEquals(): void
    {
        $a = OrderId::fromString(self::UUID);
        $b = OrderId::fromString(self::UUID);
        $c = OrderId::fromString('550e8400-e29b-41d4-a716-446655440001');

        self::assertTrue($a->equals($b));
        self::assertFalse($a->equals($c));
    }

    public function testOrderIdRejectsInvalid(): void
    {
        $this->expectException(InvalidValueException::class);
        OrderId::fromString('bad');
    }
}
