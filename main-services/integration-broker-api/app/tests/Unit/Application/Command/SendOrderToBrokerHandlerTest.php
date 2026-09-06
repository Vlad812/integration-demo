<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\SendOrderToBroker\SendOrderToBrokerHandler;
use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Message\SendOrderToBrokerMessage;
use App\Application\Shared\Broker\BrokerCreateOrderResult;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Logging\OrderFlowLogger;
use App\Tests\Unit\Support\OrderFactory;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SendOrderToBrokerHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $repository;
    private BrokerGatewayInterface&MockObject $brokerGateway;
    private SendOrderToBrokerHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->brokerGateway = $this->createMock(BrokerGatewayInterface::class);
        $this->handler = new SendOrderToBrokerHandler(
            $this->repository,
            $this->brokerGateway,
            new OrderFlowLogger($this->createMock(LoggerInterface::class)),
        );
    }

    public function testReturnsWhenOrderMissing(): void
    {
        $this->repository->method('findById')->willReturn(null);
        $this->brokerGateway->expects(self::never())->method('createOrder');
        $this->repository->expects(self::never())->method('save');

        ($this->handler)($this->message());
    }

    public function testSkipsWhenAlreadySent(): void
    {
        $this->repository->method('findById')->willReturn(OrderFactory::sentToBroker());
        $this->brokerGateway->expects(self::never())->method('createOrder');
        $this->repository->expects(self::never())->method('save');

        ($this->handler)($this->message());
    }

    public function testSuccessMarksSentToBroker(): void
    {
        $order = OrderFactory::createNew();
        $this->repository->method('findById')->willReturn($order);
        $this->brokerGateway->method('createOrder')->willReturn(new BrokerCreateOrderResult(
            brokerOrderId: 'ord_broker_1',
            brokerStatus: 'PENDING_ROUTING',
            brokerCreatedAt: new DateTimeImmutable('2026-01-15T12:00:00Z'),
            expectedCommissionCents: 150,
        ));
        $this->repository->expects(self::once())->method('save')->with($order);

        ($this->handler)($this->message());

        self::assertSame(OrderStatus::SentToBroker, $order->status());
        self::assertSame('ord_broker_1', $order->brokerOrderId());
    }

    public function testTransientMarksRetryingAndRethrows(): void
    {
        $order = OrderFactory::createNew();
        $this->repository->method('findById')->willReturn($order);
        $this->brokerGateway->method('createOrder')->willThrowException(
            BrokerTransientException::withStatus(429, 'busy'),
        );
        $this->repository->expects(self::once())->method('save')->with($order);

        try {
            ($this->handler)($this->message());
            self::fail('Expected BrokerTransientException');
        } catch (BrokerTransientException) {
            self::assertSame(OrderStatus::Retrying, $order->status());
        }
    }

    public function testFatalMarksFailedAndRethrows(): void
    {
        $order = OrderFactory::createNew();
        $this->repository->method('findById')->willReturn($order);
        $this->brokerGateway->method('createOrder')->willThrowException(
            BrokerFatalException::withStatus(400, 'bad request'),
        );
        $this->repository->expects(self::once())->method('save')->with($order);

        try {
            ($this->handler)($this->message());
            self::fail('Expected BrokerFatalException');
        } catch (BrokerFatalException) {
            self::assertSame(OrderStatus::Failed, $order->status());
            self::assertSame('HTTP_400', $order->brokerStatus());
        }
    }

    private function message(): SendOrderToBrokerMessage
    {
        return new SendOrderToBrokerMessage(
            OrderFactory::ORDER_ID,
            OrderFactory::IDEMPOTENCY_KEY,
            OrderFactory::CLIENT_ID,
        );
    }
}
