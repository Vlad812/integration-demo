<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\CreateOrder\CreateOrderCommand;
use App\Application\Command\CreateOrder\CreateOrderHandler;
use App\Application\Message\SendOrderToBrokerMessage;
use App\Application\Shared\TransactionalInterface;
use App\Domain\Exception\DuplicateIdempotencyKeyException;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Shared\UuidGeneratorInterface;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Logging\OrderFlowLogger;
use App\Tests\Unit\Support\OrderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final class CreateOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $repository;
    private UuidGeneratorInterface&MockObject $uuidGenerator;
    private MessageBusInterface&MockObject $messageBus;
    private CreateOrderHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->uuidGenerator = $this->createMock(UuidGeneratorInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $orderFlowLog = new OrderFlowLogger($this->createMock(LoggerInterface::class));

        $transactional = new class implements TransactionalInterface {
            public function execute(callable $callback): mixed
            {
                return $callback();
            }
        };

        $this->handler = new CreateOrderHandler(
            $this->repository,
            $this->uuidGenerator,
            $transactional,
            $this->messageBus,
            0,
            $orderFlowLog,
        );
    }

    public function testCreatesOrderAndDispatchesOutboxMessage(): void
    {
        $command = $this->command();

        $this->repository->method('findByIdempotencyKey')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturn(OrderFactory::ORDER_ID);

        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (SendOrderToBrokerMessage $message): bool {
                return $message->orderId === OrderFactory::ORDER_ID
                    && $message->idempotencyKey === OrderFactory::IDEMPOTENCY_KEY
                    && $message->clientId === OrderFactory::CLIENT_ID;
            }))
            ->willReturn(new Envelope(new \stdClass(), [new TransportMessageIdStamp(1)]));

        $this->repository->expects(self::exactly(2))->method('save');

        $result = ($this->handler)($command);

        self::assertFalse($result->isDuplicate);
        self::assertSame(OrderStatus::New, $result->order->status());
        self::assertSame(OrderFactory::ORDER_ID, $result->responsePayload['id']);
        self::assertSame('PROCESSING', $result->responsePayload['status']);
        self::assertNotNull($result->order->idempotencyResponse());
    }

    public function testSoftDuplicateReturnsCachedResponse(): void
    {
        $existing = OrderFactory::restore(
            idempotencyResponse: ['id' => 'cached', 'status' => 'PROCESSING', 'message' => 'ok'],
        );
        $this->repository->method('findByIdempotencyKey')->willReturn($existing);
        $this->messageBus->expects(self::never())->method('dispatch');
        $this->repository->expects(self::never())->method('save');

        $result = ($this->handler)($this->command());

        self::assertTrue($result->isDuplicate);
        self::assertSame('cached', $result->responsePayload['id']);
    }

    public function testRaceOnDuplicateIdempotencyKey(): void
    {
        $existing = OrderFactory::createNew();

        $this->repository->method('findByIdempotencyKey')
            ->willReturnOnConsecutiveCalls(null, $existing);
        $this->uuidGenerator->method('generate')->willReturn(OrderFactory::ORDER_ID);
        $this->repository->method('save')->willThrowException(
            DuplicateIdempotencyKeyException::forKey(OrderFactory::IDEMPOTENCY_KEY),
        );

        $result = ($this->handler)($this->command());

        self::assertTrue($result->isDuplicate);
        self::assertSame($existing, $result->order);
    }

    public function testFailsWhenOutboxStampMissing(): void
    {
        $this->repository->method('findByIdempotencyKey')->willReturn(null);
        $this->uuidGenerator->method('generate')->willReturn(OrderFactory::ORDER_ID);
        $this->messageBus->method('dispatch')->willReturn(new Envelope(new \stdClass()));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Outbox insert failed');

        ($this->handler)($this->command());
    }

    private function command(): CreateOrderCommand
    {
        return CreateOrderCommand::createFromRawValues(
            [
                'ticker' => 'AAPL',
                'direction' => 'BUY',
                'quantity' => 10,
                'type' => 'MARKET',
            ],
            OrderFactory::IDEMPOTENCY_KEY,
            OrderFactory::CLIENT_ID,
        );
    }
}
