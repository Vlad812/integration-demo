<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Http;

use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Http\OrderJsonPresenter;
use App\Tests\Unit\Support\OrderFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OrderJsonPresenterTest extends TestCase
{
    public function testPresentCreateAcceptedReturnsPayloadAsIs(): void
    {
        $payload = ['id' => 'x', 'status' => 'PROCESSING', 'message' => 'ok'];
        self::assertSame($payload, OrderJsonPresenter::presentCreateAccepted($payload));
    }

    #[DataProvider('statusMapProvider')]
    public function testPresentOrderDetailMapsStatus(OrderStatus $status, string $expectedApiStatus): void
    {
        $order = OrderFactory::restore(
            status: $status,
            brokerOrderId: 'ord_broker_1',
            executedQuantity: 4,
            avgPriceCents: 14520,
            totalValueCents: 58080,
        );

        $detail = OrderJsonPresenter::presentOrderDetail($order);

        self::assertSame('ord_broker_1', $detail['id']);
        self::assertSame($expectedApiStatus, $detail['status']);
        self::assertSame('AAPL', $detail['ticker']);
        self::assertSame('BUY', $detail['direction']);
        self::assertSame(10, $detail['requested_qty']);
        self::assertSame(4, $detail['executed_qty']);
        self::assertSame(14520, $detail['avg_price_cents']);
        self::assertSame(58080, $detail['total_value_cents']);
        self::assertArrayHasKey('updated_at', $detail);
    }

    /** @return iterable<string, array{OrderStatus, string}> */
    public static function statusMapProvider(): iterable
    {
        yield 'new' => [OrderStatus::New, 'PROCESSING'];
        yield 'retrying' => [OrderStatus::Retrying, 'PROCESSING'];
        yield 'sent' => [OrderStatus::SentToBroker, 'PROCESSING'];
        yield 'pending' => [OrderStatus::PendingRouting, 'PROCESSING'];
        yield 'partial' => [OrderStatus::PartiallyFilled, 'PARTIAL'];
        yield 'filled' => [OrderStatus::Filled, 'FILLED'];
        yield 'failed' => [OrderStatus::Failed, 'FAILED'];
        yield 'rejected' => [OrderStatus::Rejected, 'REJECTED'];
    }
}
