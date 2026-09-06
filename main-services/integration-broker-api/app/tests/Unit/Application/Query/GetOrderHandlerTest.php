<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query;

use App\Application\Query\GetOrder\GetOrderHandler;
use App\Application\Query\GetOrder\GetOrderQuery;
use App\Domain\Exception\ResourceNotFoundException;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Tests\Unit\Support\OrderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class GetOrderHandlerTest extends TestCase
{
    private OrderRepositoryInterface&MockObject $repository;
    private GetOrderHandler $handler;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OrderRepositoryInterface::class);
        $this->handler = new GetOrderHandler($this->repository);
    }

    public function testReturnsOrderForMatchingClient(): void
    {
        $order = OrderFactory::createNew();
        $this->repository->method('findByPublicId')->with(OrderFactory::ORDER_ID)->willReturn($order);

        $result = ($this->handler)(new GetOrderQuery(OrderFactory::ORDER_ID, OrderFactory::CLIENT_ID));

        self::assertSame($order, $result);
    }

    public function testNotFoundWhenMissing(): void
    {
        $this->repository->method('findByPublicId')->willReturn(null);

        $this->expectException(ResourceNotFoundException::class);
        ($this->handler)(new GetOrderQuery(OrderFactory::ORDER_ID, OrderFactory::CLIENT_ID));
    }

    public function testNotFoundWhenClientMismatch(): void
    {
        $order = OrderFactory::restore(clientId: 'other-client');
        $this->repository->method('findByPublicId')->willReturn($order);

        $this->expectException(ResourceNotFoundException::class);
        ($this->handler)(new GetOrderQuery(OrderFactory::ORDER_ID, OrderFactory::CLIENT_ID));
    }
}
