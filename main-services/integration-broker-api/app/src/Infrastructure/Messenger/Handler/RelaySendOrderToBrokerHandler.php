<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger\Handler;

use App\Application\Message\SendOrderToBrokerMessage;
use App\Infrastructure\Logging\OrderFlowLogger;
use App\Infrastructure\Messenger\BrokerOrderPublisher;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Stage 2: Outbox relay — reads from doctrine outbox, publishes to RabbitMQ.
 */
#[AsMessageHandler(fromTransport: 'outbox')]
final readonly class RelaySendOrderToBrokerHandler
{
    public function __construct(
        private BrokerOrderPublisher $publisher,
        private OrderFlowLogger $orderFlowLog,
    ) {
    }

    public function __invoke(SendOrderToBrokerMessage $message): void
    {
        $context = [
            'order_id' => $message->orderId,
            'client_id' => $message->clientId,
        ];
        $this->orderFlowLog->event('outbox_received', 'relay', $message->idempotencyKey, $context);
        $this->publisher->publish($message);
        $this->orderFlowLog->event('amqp_published', 'relay', $message->idempotencyKey, $context);
    }
}
