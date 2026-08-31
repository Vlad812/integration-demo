<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\EntityOrm;

use DateTimeInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\UniqueConstraint(name: 'uq_orders_client_idempotency_key', columns: ['client_id', 'idempotency_key'])]
class OrderOrm
{
    #[ORM\Id]
    #[ORM\Column(type: Types::GUID)]
    private string $id;

    #[ORM\Column(name: 'idempotency_key', type: Types::GUID)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'broker_order_id', type: Types::STRING, length: 64, nullable: true, unique: true)]
    private ?string $brokerOrderId = null;

    #[ORM\Column(name: 'client_id', type: Types::STRING, length: 64)]
    private string $clientId;

    #[ORM\Column(type: Types::STRING, length: 32)]
    private string $ticker;

    #[ORM\Column(type: Types::STRING, columnDefinition: 'order_direction NOT NULL')]
    private string $direction;

    #[ORM\Column(name: 'order_type', type: Types::STRING, columnDefinition: 'order_type NOT NULL')]
    private string $orderType;

    #[ORM\Column(type: Types::STRING, length: 3)]
    private string $currency;

    #[ORM\Column(name: 'requested_quantity', type: Types::INTEGER)]
    private int $requestedQuantity;

    #[ORM\Column(name: 'executed_quantity', type: Types::INTEGER)]
    private int $executedQuantity = 0;

    #[ORM\Column(name: 'avg_price_cents', type: Types::BIGINT, nullable: true)]
    private ?string $avgPriceCents = null;

    #[ORM\Column(name: 'total_value_cents', type: Types::BIGINT, nullable: true)]
    private ?string $totalValueCents = null;

    #[ORM\Column(name: 'expected_commission_cents', type: Types::BIGINT, nullable: true)]
    private ?string $expectedCommissionCents = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: 'order_status NOT NULL')]
    private string $status;

    #[ORM\Column(name: 'broker_status', type: Types::STRING, length: 64, nullable: true)]
    private ?string $brokerStatus = null;

    /** @var array<string, mixed>|null */
    #[ORM\Column(name: 'idempotency_response', type: Types::JSON, nullable: true)]
    private ?array $idempotencyResponse = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeInterface $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIMETZ_IMMUTABLE)]
    private DateTimeInterface $updatedAt;

    #[ORM\Column(name: 'broker_created_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeInterface $brokerCreatedAt = null;

    #[ORM\Column(name: 'broker_updated_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeInterface $brokerUpdatedAt = null;

    #[ORM\Column(name: 'last_polled_at', type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    private ?DateTimeInterface $lastPolledAt = null;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getIdempotencyKey(): string
    {
        return $this->idempotencyKey;
    }

    public function setIdempotencyKey(string $idempotencyKey): void
    {
        $this->idempotencyKey = $idempotencyKey;
    }

    public function getBrokerOrderId(): ?string
    {
        return $this->brokerOrderId;
    }

    public function setBrokerOrderId(?string $brokerOrderId): void
    {
        $this->brokerOrderId = $brokerOrderId;
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getTicker(): string
    {
        return $this->ticker;
    }

    public function setTicker(string $ticker): void
    {
        $this->ticker = $ticker;
    }

    public function getDirection(): string
    {
        return $this->direction;
    }

    public function setDirection(string $direction): void
    {
        $this->direction = $direction;
    }

    public function getOrderType(): string
    {
        return $this->orderType;
    }

    public function setOrderType(string $orderType): void
    {
        $this->orderType = $orderType;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): void
    {
        $this->currency = $currency;
    }

    public function getRequestedQuantity(): int
    {
        return $this->requestedQuantity;
    }

    public function setRequestedQuantity(int $requestedQuantity): void
    {
        $this->requestedQuantity = $requestedQuantity;
    }

    public function getExecutedQuantity(): int
    {
        return $this->executedQuantity;
    }

    public function setExecutedQuantity(int $executedQuantity): void
    {
        $this->executedQuantity = $executedQuantity;
    }

    public function getAvgPriceCents(): ?string
    {
        return $this->avgPriceCents;
    }

    public function setAvgPriceCents(?string $avgPriceCents): void
    {
        $this->avgPriceCents = $avgPriceCents;
    }

    public function getTotalValueCents(): ?string
    {
        return $this->totalValueCents;
    }

    public function setTotalValueCents(?string $totalValueCents): void
    {
        $this->totalValueCents = $totalValueCents;
    }

    public function getExpectedCommissionCents(): ?string
    {
        return $this->expectedCommissionCents;
    }

    public function setExpectedCommissionCents(?string $expectedCommissionCents): void
    {
        $this->expectedCommissionCents = $expectedCommissionCents;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getBrokerStatus(): ?string
    {
        return $this->brokerStatus;
    }

    public function setBrokerStatus(?string $brokerStatus): void
    {
        $this->brokerStatus = $brokerStatus;
    }

    /** @return array<string, mixed>|null */
    public function getIdempotencyResponse(): ?array
    {
        return $this->idempotencyResponse;
    }

    /** @param array<string, mixed>|null $idempotencyResponse */
    public function setIdempotencyResponse(?array $idempotencyResponse): void
    {
        $this->idempotencyResponse = $idempotencyResponse;
    }

    public function getCreatedAt(): DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getBrokerCreatedAt(): ?DateTimeInterface
    {
        return $this->brokerCreatedAt;
    }

    public function setBrokerCreatedAt(?DateTimeInterface $brokerCreatedAt): void
    {
        $this->brokerCreatedAt = $brokerCreatedAt;
    }

    public function getBrokerUpdatedAt(): ?DateTimeInterface
    {
        return $this->brokerUpdatedAt;
    }

    public function setBrokerUpdatedAt(?DateTimeInterface $brokerUpdatedAt): void
    {
        $this->brokerUpdatedAt = $brokerUpdatedAt;
    }

    public function getLastPolledAt(): ?DateTimeInterface
    {
        return $this->lastPolledAt;
    }

    public function setLastPolledAt(?DateTimeInterface $lastPolledAt): void
    {
        $this->lastPolledAt = $lastPolledAt;
    }
}
