<?php

declare(strict_types=1);

namespace App\Application\Command\CreateOrder;

use App\Application\Message\SendOrderToBrokerMessage;
use App\Application\Shared\TransactionalInterface;
use App\Domain\Entity\Order;
use App\Domain\Exception\DuplicateIdempotencyKeyException;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Shared\UuidGeneratorInterface;
use App\Domain\ValueObject\Currency;
use App\Domain\ValueObject\OrderId;
use App\Infrastructure\Logging\OrderFlowLogger;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportMessageIdStamp;

final readonly class CreateOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private UuidGeneratorInterface $uuidGenerator,
        private TransactionalInterface $transactional,
        private MessageBusInterface $messageBus,
        #[Autowire('%env(int:OUTBOX_DELAY_MS)%')]
        private int $outboxDelayMs,
        private OrderFlowLogger $orderFlowLog,
    ) {
    }

    public function __invoke(CreateOrderCommand $command): CreateOrderResult
    {
        try {
            /** @var CreateOrderResult $result */
            $result = $this->transactional->execute(function () use ($command): CreateOrderResult {
                $existing = $this->repository->findByIdempotencyKey(
                    $command->clientId,
                    $command->idempotencyKey,
                );

                if ($existing !== null) {
                    return $this->duplicateResult($existing);
                }

                $order = Order::create(
                    id: OrderId::fromString($this->uuidGenerator->generate()),
                    idempotencyKey: $command->idempotencyKey,
                    clientId: $command->clientId,
                    ticker: $command->ticker,
                    direction: $command->direction,
                    orderType: $command->orderType,
                    currency: Currency::usd(),
                    requestedQuantity: $command->quantity,
                );

                $this->repository->save($order);
                $stamps = [];
                if ($this->outboxDelayMs > 0) {
                    $stamps[] = new DelayStamp($this->outboxDelayMs);
                }
                $envelope = $this->messageBus->dispatch(
                    new SendOrderToBrokerMessage(
                        $order->id()->toString(),
                        $order->idempotencyKey()->toString(),
                        $order->clientId()->toString(),
                    ),
                    $stamps,
                );
                if ($envelope->last(TransportMessageIdStamp::class) === null) {
                    throw new RuntimeException('Outbox insert failed: message was not written to messenger_messages.');
                }

                $responsePayload = $this->buildResponsePayload($order);
                $order->recordIdempotencyResponse($responsePayload);
                $this->repository->save($order);

                return CreateOrderResult::created($order, $responsePayload);
            });

            $this->logCreateResult($result);

            return $result;
        } catch (DuplicateIdempotencyKeyException) {
            $existing = $this->repository->findByIdempotencyKey(
                $command->clientId,
                $command->idempotencyKey,
            );

            if ($existing === null) {
                throw new RuntimeException('Idempotency key conflict without existing order.');
            }

            $result = $this->duplicateResult($existing);
            $this->logCreateResult($result);

            return $result;
        }
    }

    private function logCreateResult(CreateOrderResult $result): void
    {
        $order = $result->order;
        $context = [
            'order_id' => $order->id()->toString(),
            'client_id' => $order->clientId()->toString(),
        ];
        $key = $order->idempotencyKey()->toString();

        if ($result->isDuplicate) {
            $this->orderFlowLog->event('idempotency_hit', 'http', $key, $context);

            return;
        }

        $this->orderFlowLog->event('order_inserted', 'http', $key, $context);
        $this->orderFlowLog->event('outbox_written', 'http', $key, $context);
    }

    private function duplicateResult(Order $order): CreateOrderResult
    {
        $cachedResponse = $order->idempotencyResponse();

        return CreateOrderResult::duplicate(
            $order,
            $cachedResponse ?? $this->buildResponsePayload($order),
        );
    }

    /** @return array{id: string, status: string, message: string} */
    private function buildResponsePayload(Order $order): array
    {
        return [
            'id' => $order->brokerOrderId() ?? $order->id()->toString(),
            'status' => 'PROCESSING',
            'message' => 'Заявка отправлена на биржу',
        ];
    }
}
