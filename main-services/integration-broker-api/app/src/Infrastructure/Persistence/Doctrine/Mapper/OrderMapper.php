<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Mapper;

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
use App\Infrastructure\Persistence\Doctrine\EntityOrm\OrderOrm;
use DateTimeImmutable;

final class OrderMapper
{
    public static function toOrm(Order $order): OrderOrm
    {
        $orm = new OrderOrm();
        self::updateOrmFromDomain($order, $orm);

        return $orm;
    }

    public static function updateOrmFromDomain(Order $order, OrderOrm $orm): void
    {
        $orm->setId($order->id()->toString());
        $orm->setIdempotencyKey($order->idempotencyKey()->toString());
        $orm->setBrokerOrderId($order->brokerOrderId());
        $orm->setClientId($order->clientId()->toString());
        $orm->setTicker($order->ticker()->toString());
        $orm->setDirection($order->direction()->value);
        $orm->setOrderType($order->orderType()->value);
        $orm->setCurrency($order->currency()->toString());
        $orm->setRequestedQuantity($order->requestedQuantity()->toInt());
        $orm->setExecutedQuantity($order->executedQuantity());
        $orm->setStatus($order->status()->value);
        $orm->setBrokerStatus($order->brokerStatus());
        $avgPriceCents = $order->avgPriceCents();
        $orm->setAvgPriceCents($avgPriceCents !== null ? (string) $avgPriceCents : null);
        $totalValueCents = $order->totalValueCents();
        $orm->setTotalValueCents($totalValueCents !== null ? (string) $totalValueCents : null);
        $expectedCommissionCents = $order->expectedCommissionCents();
        $orm->setExpectedCommissionCents($expectedCommissionCents !== null ? (string) $expectedCommissionCents : null);
        $orm->setBrokerCreatedAt($order->brokerCreatedAt());
        $orm->setBrokerUpdatedAt($order->brokerUpdatedAt());
        $orm->setLastPolledAt($order->lastPolledAt());
        $orm->setIdempotencyResponse($order->idempotencyResponse());
        $orm->setCreatedAt($order->createdAt());
        $orm->setUpdatedAt($order->updatedAt());
    }

    public static function toDomain(OrderOrm $orm): Order
    {
        $expectedCommissionCents = $orm->getExpectedCommissionCents();
        $avgPriceCents = $orm->getAvgPriceCents();
        $totalValueCents = $orm->getTotalValueCents();

        return Order::restore(
            id: OrderId::fromString($orm->getId()),
            idempotencyKey: IdempotencyKey::fromString($orm->getIdempotencyKey()),
            clientId: ClientId::fromString($orm->getClientId()),
            ticker: Ticker::fromString($orm->getTicker()),
            direction: OrderDirection::fromString($orm->getDirection()),
            orderType: OrderType::fromString($orm->getOrderType()),
            currency: Currency::fromString($orm->getCurrency()),
            requestedQuantity: Quantity::fromInt($orm->getRequestedQuantity()),
            status: OrderStatus::from($orm->getStatus()),
            executedQuantity: $orm->getExecutedQuantity(),
            brokerOrderId: $orm->getBrokerOrderId(),
            brokerStatus: $orm->getBrokerStatus(),
            avgPriceCents: $avgPriceCents !== null ? (int) $avgPriceCents : null,
            totalValueCents: $totalValueCents !== null ? (int) $totalValueCents : null,
            expectedCommissionCents: $expectedCommissionCents !== null ? (int) $expectedCommissionCents : null,
            brokerCreatedAt: $orm->getBrokerCreatedAt() !== null
                ? DateTimeImmutable::createFromInterface($orm->getBrokerCreatedAt())
                : null,
            brokerUpdatedAt: $orm->getBrokerUpdatedAt() !== null
                ? DateTimeImmutable::createFromInterface($orm->getBrokerUpdatedAt())
                : null,
            lastPolledAt: $orm->getLastPolledAt() !== null
                ? DateTimeImmutable::createFromInterface($orm->getLastPolledAt())
                : null,
            idempotencyResponse: $orm->getIdempotencyResponse(),
            createdAt: DateTimeImmutable::createFromInterface($orm->getCreatedAt()),
            updatedAt: DateTimeImmutable::createFromInterface($orm->getUpdatedAt()),
        );
    }
}
