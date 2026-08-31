<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Transactional;

use App\Application\Shared\TransactionalInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use RuntimeException;
use Throwable;

final readonly class DoctrineTransactional implements TransactionalInterface
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function execute(callable $operation): mixed
    {
        $entityManager = $this->entityManager();
        $connection = $entityManager->getConnection();
        $connection->beginTransaction();

        try {
            $result = $operation();
            $entityManager->flush();
            $connection->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }

            if (!$entityManager->isOpen()) {
                $this->managerRegistry->resetManager();
            } else {
                $entityManager->clear();
            }

            throw $exception;
        }
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
