<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Entity\Order;
use App\Domain\Exception\DuplicateIdempotencyKeyException;
use App\Domain\Exception\InvalidValueException;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\ValueObject\ClientId;
use App\Domain\ValueObject\IdempotencyKey;
use App\Domain\ValueObject\OrderId;
use App\Domain\ValueObject\OrderStatus;
use App\Infrastructure\Persistence\Doctrine\EntityOrm\OrderOrm;
use App\Infrastructure\Persistence\Doctrine\Mapper\OrderMapper;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;

final readonly class DoctrineOrderRepository implements OrderRepositoryInterface
{
    private const string IDEMPOTENCY_CONSTRAINT = 'uq_orders_client_idempotency_key';

    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function save(Order $order): void
    {
        $entityManager = $this->entityManager();
        $id = $order->id()->toString();
        $orm = $entityManager->find(OrderOrm::class, $id);

        if ($orm === null) {
            $orm = OrderMapper::toOrm($order);
            $entityManager->persist($orm);
        } else {
            OrderMapper::updateOrmFromDomain($order, $orm);
        }

        try {
            $entityManager->flush();
        } catch (UniqueConstraintViolationException $exception) {
            $haystack = $exception->getMessage();
            $previous = $exception->getPrevious();
            if ($previous !== null) {
                $haystack .= ' ' . $previous->getMessage();
            }

            if (!str_contains($haystack, self::IDEMPOTENCY_CONSTRAINT)) {
                throw $exception;
            }

            throw DuplicateIdempotencyKeyException::forKey($order->idempotencyKey()->toString());
        }
    }

    public function findById(OrderId $id): ?Order
    {
        $orm = $this->entityManager()->find(OrderOrm::class, $id->toString());

        return $orm !== null ? OrderMapper::toDomain($orm) : null;
    }

    public function findByPublicId(string $id): ?Order
    {
        $id = trim($id);
        if ($id === '') {
            return null;
        }

        try {
            $byInternalId = $this->findById(OrderId::fromString($id));
            if ($byInternalId !== null) {
                return $byInternalId;
            }
        } catch (InvalidValueException) {
        }

        return $this->findByBrokerOrderId($id);
    }

    public function findByBrokerOrderId(string $brokerOrderId): ?Order
    {
        $orm = $this->entityManager()->getRepository(OrderOrm::class)->findOneBy([
            'brokerOrderId' => $brokerOrderId,
        ]);

        return $orm !== null ? OrderMapper::toDomain($orm) : null;
    }

    public function findByIdempotencyKey(ClientId $clientId, IdempotencyKey $key): ?Order
    {
        $orm = $this->entityManager()->getRepository(OrderOrm::class)->findOneBy([
            'clientId' => $clientId->toString(),
            'idempotencyKey' => $key->toString(),
        ]);

        return $orm !== null ? OrderMapper::toDomain($orm) : null;
    }

    public function findDueForPolling(int $limit, int $minAgeSeconds): array
    {
        $pollableStatuses = [
            OrderStatus::SentToBroker->value,
            OrderStatus::PendingRouting->value,
            OrderStatus::PartiallyFilled->value,
        ];

        $qb = $this->entityManager()->createQueryBuilder();
        $qb->select('o')
            ->addSelect('CASE WHEN o.lastPolledAt IS NULL THEN 0 ELSE 1 END AS HIDDEN pollPriority')
            ->from(OrderOrm::class, 'o')
            ->where('o.brokerOrderId IS NOT NULL')
            ->andWhere($qb->expr()->in('o.status', ':statuses'))
            ->setParameter('statuses', $pollableStatuses)
            ->orderBy('pollPriority', 'ASC')
            ->addOrderBy('o.lastPolledAt', 'ASC')
            ->addOrderBy('o.createdAt', 'ASC')
            ->setMaxResults($limit);

        if ($minAgeSeconds > 0) {
            $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d seconds', $minAgeSeconds));
            $qb->andWhere('o.lastPolledAt IS NULL OR o.lastPolledAt < :threshold')
                ->setParameter('threshold', $threshold);
        }

        /** @var list<OrderOrm> $orms */
        $orms = $qb->getQuery()->getResult();

        return array_map(
            static fn (OrderOrm $orm): Order => OrderMapper::toDomain($orm),
            $orms,
        );
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = $this->managerRegistry->getManager();

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new RuntimeException('Expected Doctrine ORM EntityManager.');
        }

        return $entityManager;
    }
}
