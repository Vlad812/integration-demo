<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\EventListener;

use App\Application\Message\SendOrderToBrokerMessage;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\OrderId;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Logging\OrderFlowLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

/**
 * Stage 4: when all retries are exhausted, mark order FAILED before DLQ.
 */
#[AsEventListener(event: WorkerMessageFailedEvent::class, priority: 0)]
final readonly class MarkOrderFailedOnFinalFailureListener
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private OrderFlowLogger $orderFlowLog,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof SendOrderToBrokerMessage) {
            return;
        }

        if ($this->isLockMissOnly($event)) {
            return;
        }

        $order = $this->repository->findById(OrderId::fromString($message->orderId));

        if ($order === null) {
            return;
        }

        $status = $order->status();

        if ($status !== OrderStatus::New && $status !== OrderStatus::Retrying) {
            return;
        }

        $order->markFailed('RETRY_EXHAUSTED');
        $this->repository->save($order);

        $this->orderFlowLog->event('broker_retries_exhausted', 'broker', $message->idempotencyKey, [
            'order_id' => $message->orderId,
            'client_id' => $message->clientId,
            'error' => $event->getThrowable()->getMessage(),
        ]);
    }

    private function isLockMissOnly(WorkerMessageFailedEvent $event): bool
    {
        $throwable = $event->getThrowable();

        if (!$throwable instanceof RecoverableMessageHandlingException) {
            return false;
        }

        return str_contains($throwable->getMessage(), 'Could not acquire lock for order');
    }
}
