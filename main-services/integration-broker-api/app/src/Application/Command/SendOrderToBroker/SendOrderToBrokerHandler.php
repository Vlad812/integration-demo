<?php

declare(strict_types=1);

namespace App\Application\Command\SendOrderToBroker;

use App\Application\Message\SendOrderToBrokerMessage;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\OrderId;
use App\Infrastructure\Logging\OrderFlowLogger;

final readonly class SendOrderToBrokerHandler
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private BrokerGatewayInterface $brokerGateway,
        private OrderFlowLogger $orderFlowLog,
    ) {
    }

    public function __invoke(SendOrderToBrokerMessage $message): void
    {
        $context = [
            'order_id' => $message->orderId,
            'client_id' => $message->clientId,
        ];
        $idempotencyKey = $message->idempotencyKey;

        $order = $this->repository->findById(OrderId::fromString($message->orderId));

        if ($order === null) {
            $this->orderFlowLog->event('order_not_found', 'broker', $idempotencyKey, $context);

            return;
        }

        if ($order->status()->isTerminal() || $order->isAlreadySentToBroker()) {
            $this->orderFlowLog->event('broker_send_skipped', 'broker', $idempotencyKey, [
                ...$context,
                'status' => $order->status()->value,
                'broker_order_id' => $order->brokerOrderId(),
            ]);

            return;
        }

        $this->orderFlowLog->event('broker_http_sent', 'broker', $idempotencyKey, $context);

        $result = $this->brokerGateway->createOrder($order);

        $order->markSentToBroker(
            brokerOrderId: $result->brokerOrderId,
            brokerStatus: $result->brokerStatus,
            expectedCommissionCents: $result->expectedCommissionCents,
            brokerCreatedAt: $result->brokerCreatedAt,
        );

        $this->repository->save($order);

        $this->orderFlowLog->event('broker_accepted', 'broker', $idempotencyKey, [
            ...$context,
            'broker_order_id' => $result->brokerOrderId,
            'broker_status' => $result->brokerStatus,
        ]);
    }
}
