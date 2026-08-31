<?php

declare(strict_types=1);

namespace App\Application\Command\CreateOrder;

use App\Domain\Entity\Order;

final readonly class CreateOrderResult
{
    /** @param array<string, mixed> $responsePayload */
    private function __construct(
        public Order $order,
        public bool $isDuplicate,
        public array $responsePayload,
    ) {
    }

    /** @param array<string, mixed> $responsePayload */
    public static function created(Order $order, array $responsePayload): self
    {
        return new self($order, false, $responsePayload);
    }

    /** @param array<string, mixed> $responsePayload */
    public static function duplicate(Order $order, array $responsePayload): self
    {
        return new self($order, true, $responsePayload);
    }
}
