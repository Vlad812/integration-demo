<?php

declare(strict_types=1);

namespace App\Application\Command\PollOrderStatuses;

use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Message\PollOrderStatusesMessage;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\OrderId;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Logging\OrderFlowLogger;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(fromTransport: 'scheduler_order_polling')]
final readonly class PollOrderStatusesHandler
{
    public function __construct(
        private OrderRepositoryInterface $repository,
        private BrokerGatewayInterface $brokerGateway,
        private LockFactory $lockFactory,
        private OrderFlowLogger $orderFlowLog,
        private LoggerInterface $logger,
        #[Autowire('%env(int:POLL_BATCH_SIZE)%')]
        private int $batchSize,
        #[Autowire('%env(int:POLL_MIN_AGE_SECONDS)%')]
        private int $minAgeSeconds,
    ) {
    }

    public function __invoke(PollOrderStatusesMessage $message): void
    {
        $orders = $this->repository->findDueForPolling($this->batchSize, $this->minAgeSeconds);

        $this->orderFlowLog->event('poll_tick', 'poll', '', [
            'batch_size' => count($orders),
        ]);

        foreach ($orders as $order) {
            $orderId = $order->id()->toString();
            $lock = $this->lockFactory->createLock(
                sprintf('order-poll-%s', $orderId),
                ttl: 120.0,
            );

            if (!$lock->acquire()) {
                continue;
            }

            try {
                $this->pollSingleOrder($orderId);
            } catch (\Throwable $exception) {
                $this->logger->error('Poll tick failed for order.', [
                    'order_id' => $orderId,
                    'error' => $exception->getMessage(),
                ]);
            } finally {
                $lock->release();
            }
        }
    }

    private function pollSingleOrder(string $orderId): void
    {
        $order = $this->repository->findById(OrderId::fromString($orderId));

        if ($order === null || !$order->isPollable()) {
            return;
        }

        $brokerOrderId = $order->brokerOrderId();

        if ($brokerOrderId === null) {
            return;
        }

        $now = new DateTimeImmutable();
        $idempotencyKey = $order->idempotencyKey()->toString();
        $context = [
            'order_id' => $orderId,
            'broker_order_id' => $brokerOrderId,
            'client_id' => $order->clientId()->toString(),
        ];

        try {
            $snapshot = $this->brokerGateway->getOrder($brokerOrderId);

            $this->orderFlowLog->event('poll_fetched', 'poll', $idempotencyKey, [
                ...$context,
                'broker_status' => $snapshot->brokerStatus,
            ]);

            $applied = $order->applyBrokerExecution(
                brokerStatus: $snapshot->brokerStatus,
                executedQuantity: $snapshot->executedQuantity,
                avgPriceCents: $snapshot->avgPriceCents,
                totalValueCents: $snapshot->totalValueCents,
                brokerUpdatedAt: $snapshot->updatedAt,
            );

            $order->markPolled($now);
            $this->repository->save($order);

            if ($applied) {
                $this->orderFlowLog->event('poll_updated', 'poll', $idempotencyKey, [
                    ...$context,
                    'status' => $order->status()->value,
                    'executed_qty' => $order->executedQuantity(),
                ]);
            } elseif (OrderStatus::fromBrokerStatus($snapshot->brokerStatus) === null) {
                $this->orderFlowLog->event('poll_unknown_status', 'poll', $idempotencyKey, [
                    ...$context,
                    'broker_status' => $snapshot->brokerStatus,
                ]);
            } else {
                $this->orderFlowLog->event('poll_unchanged', 'poll', $idempotencyKey, $context);
            }
        } catch (BrokerTransientException $exception) {
            $order->markPolled($now);
            $this->repository->save($order);

            $this->orderFlowLog->event('poll_transient', 'poll', $idempotencyKey, [
                ...$context,
                'error' => $exception->getMessage(),
            ]);
        } catch (BrokerFatalException $exception) {
            $order->markPolled($now);
            $this->repository->save($order);

            $this->logger->error('Poll fatal error for order.', [
                ...$context,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
