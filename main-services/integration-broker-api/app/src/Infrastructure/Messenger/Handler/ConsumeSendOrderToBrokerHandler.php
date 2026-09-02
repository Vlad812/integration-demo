<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Command\SendOrderToBroker\SendOrderToBrokerHandler;
use App\Application\Exception\BrokerFatalException;
use App\Application\Message\SendOrderToBrokerMessage;
use App\Infrastructure\Logging\OrderFlowLogger;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * Stage 3–4: RabbitMQ consumer — sends order to external broker API, handles response outcomes.
 */
#[AsMessageHandler(fromTransport: 'broker')]
final readonly class ConsumeSendOrderToBrokerHandler
{
    public function __construct(
        private SendOrderToBrokerHandler $handler,
        private LockFactory $lockFactory,
        private OrderFlowLogger $orderFlowLog,
    ) {
    }

    public function __invoke(SendOrderToBrokerMessage $message): void
    {
        $lock = $this->lockFactory->createLock(
            sprintf('order-send-%s', $message->orderId),
            ttl: 120.0,
        );

        if (!$lock->acquire()) {
            throw new RecoverableMessageHandlingException(sprintf(
                'Could not acquire lock for order "%s".',
                $message->orderId,
            ));
        }

        try {
            $this->orderFlowLog->event('broker_message_received', 'broker', $message->idempotencyKey, [
                'order_id' => $message->orderId,
                'client_id' => $message->clientId,
            ]);

            ($this->handler)($message);
        } catch (BrokerFatalException $exception) {
            throw new UnrecoverableMessageHandlingException(
                $exception->getMessage(),
                0,
                $exception,
            );
        } finally {
            $lock->release();
        }
    }
}
