<?php

declare(strict_types=1);

namespace App\Application\Shared\Broker;

use DateTimeImmutable;

final readonly class BrokerOrderSnapshot
{
    public function __construct(
        public string $brokerOrderId,
        public string $brokerStatus,
        public string $ticker,
        public string $side,
        public int $requestedQuantity,
        public int $executedQuantity,
        public ?int $avgPriceCents,
        public ?int $totalValueCents,
        public string $currency,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
