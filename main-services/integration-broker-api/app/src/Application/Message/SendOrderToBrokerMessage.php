<?php

declare(strict_types=1);

namespace App\Application\Message;

final readonly class SendOrderToBrokerMessage
{
    public function __construct(
        public string $orderId,
        public string $idempotencyKey,
        public string $clientId,
    ) {
    }
}
