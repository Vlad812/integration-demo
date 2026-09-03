<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Order;
use App\Domain\ValueObject\ClientId;
use App\Domain\ValueObject\IdempotencyKey;
use App\Domain\ValueObject\OrderId;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findById(OrderId $id): ?Order;

    /** Resolves GET /orders/{id}: internal UUID or broker_order_id. */
    public function findByPublicId(string $id): ?Order;

    public function findByBrokerOrderId(string $brokerOrderId): ?Order;

    public function findByIdempotencyKey(ClientId $clientId, IdempotencyKey $key): ?Order;

    /** @return list<Order> */
    public function findDueForPolling(int $limit, int $minAgeSeconds): array;
}
