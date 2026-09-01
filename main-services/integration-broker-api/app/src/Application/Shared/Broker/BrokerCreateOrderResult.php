<?php

declare(strict_types=1);

namespace App\Application\Shared\Broker;

use DateTimeImmutable;

final readonly class BrokerCreateOrderResult
{
    public function __construct(
        public string $brokerOrderId,
        public string $brokerStatus,
        public DateTimeImmutable $brokerCreatedAt,
        public ?int $expectedCommissionCents,
    ) {
    }
}
