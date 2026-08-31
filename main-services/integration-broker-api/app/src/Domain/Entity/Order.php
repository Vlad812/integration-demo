<?php

declare(strict_types=1);

namespace App\Domain\Entity;

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

final class Order
{
    /** @param array<string, mixed>|null $idempotencyResponse */
    private function __construct(
        private readonly OrderId $id,
        private readonly IdempotencyKey $idempotencyKey,
        private readonly ClientId $clientId,
        private readonly Ticker $ticker,
        private readonly OrderDirection $direction,
        private readonly OrderType $orderType,
        private readonly Currency $currency,
        private readonly Quantity $requestedQuantity,
        private OrderStatus $status,
        private int $executedQuantity,
        private ?string $brokerOrderId,
        private ?array $idempotencyResponse,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {
    }

    public static function create(
        OrderId $id,
        IdempotencyKey $idempotencyKey,
        ClientId $clientId,
        Ticker $ticker,
        OrderDirection $direction,
        OrderType $orderType,
        Currency $currency,
        Quantity $requestedQuantity,
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: $id,
            idempotencyKey: $idempotencyKey,
            clientId: $clientId,
            ticker: $ticker,
            direction: $direction,
            orderType: $orderType,
            currency: $currency,
            requestedQuantity: $requestedQuantity,
            status: OrderStatus::New,
            executedQuantity: 0,
            brokerOrderId: null,
            idempotencyResponse: null,
            createdAt: $now,
            updatedAt: $now,
        );
    }

    /** @param array<string, mixed>|null $idempotencyResponse */
    public static function restore(
        OrderId $id,
        IdempotencyKey $idempotencyKey,
        ClientId $clientId,
        Ticker $ticker,
        OrderDirection $direction,
        OrderType $orderType,
        Currency $currency,
        Quantity $requestedQuantity,
        OrderStatus $status,
        int $executedQuantity,
        ?string $brokerOrderId,
        ?array $idempotencyResponse,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self(
            id: $id,
            idempotencyKey: $idempotencyKey,
            clientId: $clientId,
            ticker: $ticker,
            direction: $direction,
            orderType: $orderType,
            currency: $currency,
            requestedQuantity: $requestedQuantity,
            status: $status,
            executedQuantity: $executedQuantity,
            brokerOrderId: $brokerOrderId,
            idempotencyResponse: $idempotencyResponse,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
    }

    /** @param array<string, mixed> $response */
    public function recordIdempotencyResponse(array $response): void
    {
        $this->idempotencyResponse = $response;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function idempotencyKey(): IdempotencyKey
    {
        return $this->idempotencyKey;
    }

    public function clientId(): ClientId
    {
        return $this->clientId;
    }

    public function ticker(): Ticker
    {
        return $this->ticker;
    }

    public function direction(): OrderDirection
    {
        return $this->direction;
    }

    public function orderType(): OrderType
    {
        return $this->orderType;
    }

    public function currency(): Currency
    {
        return $this->currency;
    }

    public function requestedQuantity(): Quantity
    {
        return $this->requestedQuantity;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function executedQuantity(): int
    {
        return $this->executedQuantity;
    }

    public function brokerOrderId(): ?string
    {
        return $this->brokerOrderId;
    }

    /** @return array<string, mixed>|null */
    public function idempotencyResponse(): ?array
    {
        return $this->idempotencyResponse;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
