<?php

declare(strict_types=1);

namespace App\Tests\Unit\Support;

use App\Domain\Entity\Order;
use App\Domain\ValueObject\ClientId;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\IdempotencyKey;
use App\Domain\ValueObject\OrderDirection;
use App\Domain\ValueObject\OrderId;
use App\Domain\ValueObject\OrderStatus;
use App\Domain\ValueObject\OrderType;
use App\Domain\ValueObject\Quantity;
use App\Domain\ValueObject\Ticker;
use DateTimeImmutable;

final class OrderFactory
{
    public const string ORDER_ID = '11111111-1111-4111-8111-111111111111';
    public const string IDEMPOTENCY_KEY = '22222222-2222-4222-8222-222222222222';
    public const string CLIENT_ID = 'client-demo-1';

    public static function createNew(
        int $quantity = 10,
        string $ticker = 'AAPL',
    ): Order {
        return Order::create(
            id: OrderId::fromString(self::ORDER_ID),
            idempotencyKey: IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            clientId: ClientId::fromString(self::CLIENT_ID),
            ticker: Ticker::fromString($ticker),
            direction: OrderDirection::Buy,
            orderType: OrderType::Market,
            currency: Currency::usd(),
            requestedQuantity: Quantity::fromInt($quantity),
        );
    }

    public static function restore(
        OrderStatus $status = OrderStatus::New,
        ?string $brokerOrderId = null,
        int $executedQuantity = 0,
        ?string $brokerStatus = null,
        ?int $avgPriceCents = null,
        ?int $totalValueCents = null,
        ?array $idempotencyResponse = null,
        string $clientId = self::CLIENT_ID,
        int $quantity = 10,
    ): Order {
        $now = new DateTimeImmutable('2026-01-15T10:00:00Z');

        return Order::restore(
            id: OrderId::fromString(self::ORDER_ID),
            idempotencyKey: IdempotencyKey::fromString(self::IDEMPOTENCY_KEY),
            clientId: ClientId::fromString($clientId),
            ticker: Ticker::fromString('AAPL'),
            direction: OrderDirection::Buy,
            orderType: OrderType::Market,
            currency: Currency::usd(),
            requestedQuantity: Quantity::fromInt($quantity),
            status: $status,
            executedQuantity: $executedQuantity,
            brokerOrderId: $brokerOrderId,
            brokerStatus: $brokerStatus,
            avgPriceCents: $avgPriceCents,
            totalValueCents: $totalValueCents,
            expectedCommissionCents: null,
            brokerCreatedAt: $brokerOrderId !== null ? $now : null,
            brokerUpdatedAt: null,
            lastPolledAt: null,
            idempotencyResponse: $idempotencyResponse,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    public static function sentToBroker(
        string $brokerOrderId = 'ord_broker_1',
        OrderStatus $status = OrderStatus::SentToBroker,
    ): Order {
        return self::restore(
            status: $status,
            brokerOrderId: $brokerOrderId,
            brokerStatus: 'PENDING_ROUTING',
        );
    }
}
