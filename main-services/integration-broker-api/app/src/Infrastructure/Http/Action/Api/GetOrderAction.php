<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Action\Api;

use App\Application\Query\GetOrder\GetOrderHandler;
use App\Application\Query\GetOrder\GetOrderQuery;
use App\Infrastructure\Http\Action\AbstractAction;
use App\Infrastructure\Http\OrderJsonPresenter;
use App\Infrastructure\Security\ClientIdProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/v1/investments/orders/{id}',
    name: 'api_v1_investments_orders_get',
    requirements: ['id' => '.+'],
    methods: ['GET'],
)]
final class GetOrderAction extends AbstractAction
{
    public function __construct(
        LoggerInterface $logger,
        private readonly GetOrderHandler $handler,
        private readonly ClientIdProvider $clientIdProvider,
    ) {
        parent::__construct($logger);
    }

    protected function handleRequest(Request $request): Response
    {
        $order = ($this->handler)(new GetOrderQuery(
            publicId: (string) $request->attributes->get('id'),
            clientId: $this->clientIdProvider->getClientId(),
        ));

        return $this->respondJson(OrderJsonPresenter::presentOrderDetail($order));
    }
}
