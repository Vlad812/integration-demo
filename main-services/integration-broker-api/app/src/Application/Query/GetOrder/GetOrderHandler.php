<?php

declare(strict_types=1);

namespace App\Application\Query\GetOrder;

use App\Domain\Entity\Order;
use App\Domain\Exception\ResourceNotFoundException;
use App\Domain\Repository\OrderRepositoryInterface;

final readonly class GetOrderHandler
{
    public function __construct(
        private OrderRepositoryInterface $repository,
    ) {
    }

    public function __invoke(GetOrderQuery $query): Order
    {
        $order = $this->repository->findByPublicId($query->publicId);

        if ($order === null) {
            throw ResourceNotFoundException::withId($query->publicId);
        }

        if ($order->clientId()->toString() !== $query->clientId) {
            throw ResourceNotFoundException::withId($query->publicId);
        }

        return $order;
    }
}
