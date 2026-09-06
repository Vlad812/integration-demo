<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Persistence\Doctrine\Mapper\OrderMapper;
use App\Tests\Unit\Support\OrderFactory;
use PHPUnit\Framework\TestCase;

final class OrderMapperTest extends TestCase
{
    public function testRoundTripPreservesDomainFields(): void
    {
        $original = OrderFactory::restore(
            status: OrderStatus::PartiallyFilled,
            brokerOrderId: 'ord_broker_1',
            executedQuantity: 4,
            brokerStatus: 'PARTIALLY_FILLED',
            avgPriceCents: 14520,
            totalValueCents: 58080,
            idempotencyResponse: ['id' => 'ord_broker_1', 'status' => 'PROCESSING', 'message' => 'ok'],
        );

        $orm = OrderMapper::toOrm($original);
        $restored = OrderMapper::toDomain($orm);

        self::assertTrue($original->id()->equals($restored->id()));
        self::assertSame($original->idempotencyKey()->toString(), $restored->idempotencyKey()->toString());
        self::assertSame($original->clientId()->toString(), $restored->clientId()->toString());
        self::assertSame($original->ticker()->toString(), $restored->ticker()->toString());
        self::assertSame($original->direction(), $restored->direction());
        self::assertSame($original->orderType(), $restored->orderType());
        self::assertSame($original->currency()->toString(), $restored->currency()->toString());
        self::assertSame($original->requestedQuantity()->toInt(), $restored->requestedQuantity()->toInt());
        self::assertSame($original->status(), $restored->status());
        self::assertSame($original->executedQuantity(), $restored->executedQuantity());
        self::assertSame($original->brokerOrderId(), $restored->brokerOrderId());
        self::assertSame($original->brokerStatus(), $restored->brokerStatus());
        self::assertSame($original->avgPriceCents(), $restored->avgPriceCents());
        self::assertSame($original->totalValueCents(), $restored->totalValueCents());
        self::assertSame($original->idempotencyResponse(), $restored->idempotencyResponse());
    }

    public function testRoundTripForNewOrder(): void
    {
        $original = OrderFactory::createNew();
        $restored = OrderMapper::toDomain(OrderMapper::toOrm($original));

        self::assertSame(OrderStatus::New, $restored->status());
        self::assertNull($restored->brokerOrderId());
        self::assertSame(0, $restored->executedQuantity());
    }
}
