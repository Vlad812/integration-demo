<?php

declare(strict_types=1);

namespace App\Infrastructure\Broker;

use App\Domain\Entity\Order;

final class BrokerOrderRequestMapper
{
    /** @return array<string, mixed> */
    public static function toBrokerPayload(Order $order): array
    {
        return [
            'client_id' => $order->clientId()->toString(),
            'instrument_ticker' => $order->ticker()->toString(),
            'side' => $order->direction()->value,
            'quantity' => $order->requestedQuantity()->toInt(),
            'order_type' => $order->orderType()->value,
            'currency' => $order->currency()->toString(),
        ];
    }
}
