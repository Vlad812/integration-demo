<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Exception\BusinessRuleViolationException;
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
        private readonly OrderId           $id,
        private readonly IdempotencyKey    $idempotencyKey,
        private readonly ClientId          $clientId,
        private readonly Ticker            $ticker,
        private readonly OrderDirection    $direction,
        private readonly OrderType         $orderType,
        private readonly Currency          $currency,
        private readonly Quantity          $requestedQuantity,
        private OrderStatus                $status,
        private readonly int               $executedQuantity,
        private ?string                    $brokerOrderId,
        private ?string                    $brokerStatus,
        private ?int                       $expectedCommissionCents,
        private ?DateTimeImmutable         $brokerCreatedAt,
        private ?array                     $idempotencyResponse,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable          $updatedAt,
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
            brokerStatus: null,
            expectedCommissionCents: null,
            brokerCreatedAt: null,
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
        ?string $brokerStatus,
        ?int $expectedCommissionCents,
        ?DateTimeImmutable $brokerCreatedAt,
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
            brokerStatus: $brokerStatus,
            expectedCommissionCents: $expectedCommissionCents,
            brokerCreatedAt: $brokerCreatedAt,
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

    public function markSentToBroker(
        string $brokerOrderId,
        string $brokerStatus,
        ?int $expectedCommissionCents,
        DateTimeImmutable $brokerCreatedAt,
    ): void {
        if ($this->brokerOrderId !== null) {
            return;
        }

        if ($this->status !== OrderStatus::New && $this->status !== OrderStatus::Retrying) {
            throw new BusinessRuleViolationException(sprintf(
                'Order "%s" cannot be sent to broker from status "%s".',
                $this->id->toString(),
                $this->status->value,
            ));
        }

        $this->brokerOrderId = $brokerOrderId;
        $this->brokerStatus = $brokerStatus;
        $this->expectedCommissionCents = $expectedCommissionCents;
        $this->brokerCreatedAt = $brokerCreatedAt;
        $this->status = OrderStatus::SentToBroker;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markRetrying(): void
    {
        if ($this->brokerOrderId !== null) {
            return;
        }

        if ($this->status === OrderStatus::Retrying) {
            return;
        }

        if ($this->status !== OrderStatus::New) {
            throw new BusinessRuleViolationException(sprintf(
                'Order "%s" cannot be marked retrying from status "%s".',
                $this->id->toString(),
                $this->status->value,
            ));
        }

        $this->status = OrderStatus::Retrying;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function markFailed(?string $reason = null): void
    {
        if ($this->status === OrderStatus::Failed
            || $this->status === OrderStatus::Rejected
            || $this->status === OrderStatus::Filled
        ) {
            return;
        }

        if ($this->status !== OrderStatus::New && $this->status !== OrderStatus::Retrying) {
            throw new BusinessRuleViolationException(sprintf(
                'Order "%s" cannot be marked failed from status "%s".',
                $this->id->toString(),
                $this->status->value,
            ));
        }

        if ($reason !== null && $reason !== '') {
            $this->brokerStatus = $reason;
        }

        $this->status = OrderStatus::Failed;
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isAlreadySentToBroker(): bool
    {
        return $this->brokerOrderId !== null;
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

    public function brokerStatus(): ?string
    {
        return $this->brokerStatus;
    }

    public function expectedCommissionCents(): ?int
    {
        return $this->expectedCommissionCents;
    }

    public function brokerCreatedAt(): ?DateTimeImmutable
    {
        return $this->brokerCreatedAt;
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
