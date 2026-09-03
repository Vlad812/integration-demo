<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Domain\Entity\Order;
use App\Domain\ValueObject\OrderStatus;

final class OrderJsonPresenter
{
    /** @param array<string, mixed> $payload */
    public static function presentCreateAccepted(array $payload): array
    {
        return $payload;
    }

    /** @return array<string, mixed> */
    public static function presentOrderDetail(Order $order): array
    {
        $updatedAt = $order->brokerUpdatedAt() ?? $order->updatedAt();

        return [
            'id' => $order->brokerOrderId() ?? $order->id()->toString(),
            'status' => self::mapStatusForApi($order->status()),
            'ticker' => $order->ticker()->toString(),
            'direction' => $order->direction()->value,
            'requested_qty' => $order->requestedQuantity()->toInt(),
            'executed_qty' => $order->executedQuantity(),
            'avg_price_cents' => $order->avgPriceCents(),
            'total_value_cents' => $order->totalValueCents(),
            'updated_at' => $updatedAt->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    private static function mapStatusForApi(OrderStatus $status): string
    {
        return match ($status) {
            OrderStatus::New,
            OrderStatus::Retrying,
            OrderStatus::SentToBroker,
            OrderStatus::PendingRouting => 'PROCESSING',
            OrderStatus::PartiallyFilled => 'PARTIAL',
            OrderStatus::Filled => 'FILLED',
            OrderStatus::Failed => 'FAILED',
            OrderStatus::Rejected => 'REJECTED',
        };
    }
}
