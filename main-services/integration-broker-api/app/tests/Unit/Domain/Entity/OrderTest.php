<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Exception\BusinessRuleViolationException;
use App\Domain\ValueObject\OrderStatus;
use App\Tests\Unit\Support\OrderFactory;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class OrderTest extends TestCase
{
    public function testCreateStartsAsNewWithEmptyBrokerFields(): void
    {
        $order = OrderFactory::createNew();

        self::assertSame(OrderStatus::New, $order->status());
        self::assertSame(0, $order->executedQuantity());
        self::assertNull($order->brokerOrderId());
        self::assertNull($order->brokerStatus());
        self::assertFalse($order->isAlreadySentToBroker());
        self::assertFalse($order->isPollable());
    }

    public function testMarkSentToBrokerFromNew(): void
    {
        $order = OrderFactory::createNew();
        $createdAt = new DateTimeImmutable('2026-01-15T12:00:00Z');

        $order->markSentToBroker('ord_1', 'PENDING_ROUTING', 150, $createdAt);

        self::assertSame(OrderStatus::SentToBroker, $order->status());
        self::assertSame('ord_1', $order->brokerOrderId());
        self::assertSame('PENDING_ROUTING', $order->brokerStatus());
        self::assertSame(150, $order->expectedCommissionCents());
        self::assertTrue($order->isAlreadySentToBroker());
        self::assertTrue($order->isPollable());
    }

    public function testMarkSentToBrokerIsIdempotentWhenAlreadySent(): void
    {
        $order = OrderFactory::sentToBroker('ord_1');
        $order->markSentToBroker('ord_2', 'FILLED', 200, new DateTimeImmutable());

        self::assertSame('ord_1', $order->brokerOrderId());
        self::assertSame(OrderStatus::SentToBroker, $order->status());
    }

    public function testMarkSentToBrokerRejectsIllegalStatus(): void
    {
        $order = OrderFactory::restore(status: OrderStatus::Filled, brokerOrderId: null);

        $this->expectException(BusinessRuleViolationException::class);
        $order->markSentToBroker('ord_1', 'PENDING_ROUTING', null, new DateTimeImmutable());
    }

    public function testMarkRetryingFromNew(): void
    {
        $order = OrderFactory::createNew();
        $order->markRetrying();

        self::assertSame(OrderStatus::Retrying, $order->status());
    }

    public function testMarkRetryingNoOpWhenAlreadyRetryingOrSent(): void
    {
        $retrying = OrderFactory::restore(status: OrderStatus::Retrying);
        $retrying->markRetrying();
        self::assertSame(OrderStatus::Retrying, $retrying->status());

        $sent = OrderFactory::sentToBroker();
        $sent->markRetrying();
        self::assertSame(OrderStatus::SentToBroker, $sent->status());
    }

    public function testMarkRetryingRejectsIllegalStatus(): void
    {
        $order = OrderFactory::restore(status: OrderStatus::Failed, brokerOrderId: null);

        $this->expectException(BusinessRuleViolationException::class);
        $order->markRetrying();
    }

    public function testMarkFailedFromNewWithReason(): void
    {
        $order = OrderFactory::createNew();
        $order->markFailed('HTTP_400');

        self::assertSame(OrderStatus::Failed, $order->status());
        self::assertSame('HTTP_400', $order->brokerStatus());
    }

    public function testMarkFailedNoOpWhenTerminal(): void
    {
        $order = OrderFactory::restore(status: OrderStatus::Filled, brokerOrderId: 'ord_1');
        $order->markFailed('HTTP_400');

        self::assertSame(OrderStatus::Filled, $order->status());
    }

    public function testMarkFailedRejectsIllegalStatus(): void
    {
        $order = OrderFactory::sentToBroker();

        $this->expectException(BusinessRuleViolationException::class);
        $order->markFailed('HTTP_400');
    }

    public function testApplyBrokerExecutionHappyPath(): void
    {
        $order = OrderFactory::sentToBroker();
        $updatedAt = new DateTimeImmutable('2026-01-15T13:00:00Z');

        $applied = $order->applyBrokerExecution(
            brokerStatus: 'PARTIALLY_FILLED',
            executedQuantity: 4,
            avgPriceCents: 14520,
            totalValueCents: 58080,
            brokerUpdatedAt: $updatedAt,
        );

        self::assertTrue($applied);
        self::assertSame(OrderStatus::PartiallyFilled, $order->status());
        self::assertSame(4, $order->executedQuantity());
        self::assertSame(14520, $order->avgPriceCents());
        self::assertSame($updatedAt, $order->brokerUpdatedAt());
    }

    public function testApplyBrokerExecutionTransitionToFilled(): void
    {
        $order = OrderFactory::restore(
            status: OrderStatus::PartiallyFilled,
            brokerOrderId: 'ord_1',
            executedQuantity: 4,
            brokerStatus: 'PARTIALLY_FILLED',
        );

        $applied = $order->applyBrokerExecution(
            brokerStatus: 'FILLED',
            executedQuantity: 10,
            avgPriceCents: 14500,
            totalValueCents: 145000,
            brokerUpdatedAt: new DateTimeImmutable(),
        );

        self::assertTrue($applied);
        self::assertSame(OrderStatus::Filled, $order->status());
        self::assertFalse($order->isPollable());
    }

    public function testApplyBrokerExecutionReturnsFalseForTerminal(): void
    {
        $order = OrderFactory::restore(status: OrderStatus::Filled, brokerOrderId: 'ord_1');

        self::assertFalse($order->applyBrokerExecution(
            'FILLED',
            10,
            null,
            null,
            new DateTimeImmutable(),
        ));
    }

    public function testApplyBrokerExecutionReturnsFalseWithoutBrokerId(): void
    {
        $order = OrderFactory::createNew();

        self::assertFalse($order->applyBrokerExecution(
            'FILLED',
            10,
            null,
            null,
            new DateTimeImmutable(),
        ));
    }

    public function testApplyBrokerExecutionReturnsFalseForUnknownStatus(): void
    {
        $order = OrderFactory::sentToBroker();

        self::assertFalse($order->applyBrokerExecution(
            'UNKNOWN',
            1,
            null,
            null,
            new DateTimeImmutable(),
        ));
    }

    public function testApplyBrokerExecutionReturnsFalseForBadQuantity(): void
    {
        $order = OrderFactory::sentToBroker();

        self::assertFalse($order->applyBrokerExecution(
            'FILLED',
            11,
            null,
            null,
            new DateTimeImmutable(),
        ));
        self::assertFalse($order->applyBrokerExecution(
            'FILLED',
            -1,
            null,
            null,
            new DateTimeImmutable(),
        ));
    }

    public function testRecordIdempotencyResponse(): void
    {
        $order = OrderFactory::createNew();
        $payload = ['id' => 'x', 'status' => 'PROCESSING', 'message' => 'ok'];

        $order->recordIdempotencyResponse($payload);

        self::assertSame($payload, $order->idempotencyResponse());
    }

    public function testMarkPolled(): void
    {
        $order = OrderFactory::sentToBroker();
        $polledAt = new DateTimeImmutable('2026-01-15T14:00:00Z');

        $order->markPolled($polledAt);

        self::assertSame($polledAt, $order->lastPolledAt());
    }
}
