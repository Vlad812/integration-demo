<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\PollOrderStatuses\PollOrderStatusesHandler;
use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Message\PollOrderStatusesMessage;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Application\Shared\Broker\BrokerOrderSnapshot;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Logging\OrderFlowLogger;
use App\Tests\Unit\Support\OrderFactory;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;

final class PollOrderStatusesHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $repository;
    private BrokerGatewayInterface&MockObject $brokerGateway;
    private LockFactory&MockObject $lockFactory;
    private LoggerInterface&MockObject $logger;
    private PollOrderStatusesHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->brokerGateway = $this->createMock(BrokerGatewayInterface::class);
        $this->lockFactory = $this->createMock(LockFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new PollOrderStatusesHandler(
            $this->repository,
            $this->brokerGateway,
            $this->lockFactory,
            new OrderFlowLogger($this->createMock(LoggerInterface::class)),
            $this->logger,
            10,
            5,
        );
    }

    public function testSkipsWhenLockNotAcquired(): void
    {
        $order = OrderFactory::sentToBroker();
        $this->repository->method('findDueForPolling')->willReturn([$order]);
        $this->stubLock(acquired: false);
        $this->brokerGateway->expects(self::never())->method('getOrder');
        $this->repository->expects(self::never())->method('save');

        ($this->handler)(new PollOrderStatusesMessage());
    }

    public function testAppliesSnapshotAndMarksPolled(): void
    {
        $order = OrderFactory::sentToBroker();
        $this->repository->method('findDueForPolling')->willReturn([$order]);
        $this->repository->method('findById')->willReturn($order);
        $this->stubLock(acquired: true);

        $this->brokerGateway->method('getOrder')->willReturn(new BrokerOrderSnapshot(
            brokerOrderId: 'ord_broker_1',
            brokerStatus: 'PARTIALLY_FILLED',
            ticker: 'AAPL',
            side: 'BUY',
            requestedQuantity: 10,
            executedQuantity: 4,
            avgPriceCents: 14520,
            totalValueCents: 58080,
            currency: 'USD',
            updatedAt: new DateTimeImmutable('2026-01-15T13:00:00Z'),
        ));

        $this->repository->expects(self::once())->method('save')->with($order);

        ($this->handler)(new PollOrderStatusesMessage());

        self::assertSame(OrderStatus::PartiallyFilled, $order->status());
        self::assertSame(4, $order->executedQuantity());
        self::assertNotNull($order->lastPolledAt());
    }

    public function testTransientStillMarksPolledWithoutRethrow(): void
    {
        $order = OrderFactory::sentToBroker();
        $this->repository->method('findDueForPolling')->willReturn([$order]);
        $this->repository->method('findById')->willReturn($order);
        $this->stubLock(acquired: true);
        $this->brokerGateway->method('getOrder')->willThrowException(
            BrokerTransientException::withStatus(503, 'unavailable'),
        );
        $this->repository->expects(self::once())->method('save')->with($order);

        ($this->handler)(new PollOrderStatusesMessage());

        self::assertSame(OrderStatus::SentToBroker, $order->status());
        self::assertNotNull($order->lastPolledAt());
    }

    public function testFatalStillMarksPolledWithoutRethrow(): void
    {
        $order = OrderFactory::sentToBroker();
        $this->repository->method('findDueForPolling')->willReturn([$order]);
        $this->repository->method('findById')->willReturn($order);
        $this->stubLock(acquired: true);
        $this->brokerGateway->method('getOrder')->willThrowException(
            BrokerFatalException::withStatus(403, 'forbidden'),
        );
        $this->repository->expects(self::once())->method('save')->with($order);
        $this->logger->expects(self::once())->method('error');

        ($this->handler)(new PollOrderStatusesMessage());

        self::assertNotNull($order->lastPolledAt());
    }

    private function stubLock(bool $acquired): void
    {
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn($acquired);
        $lock->expects($acquired ? self::once() : self::never())->method('release');
        $this->lockFactory->method('createLock')->willReturn($lock);
    }
}
