<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Action\Api;

use App\Application\Command\CreateOrder\CreateOrderCommand;
use App\Application\Command\CreateOrder\CreateOrderHandler;
use App\Infrastructure\Http\Action\AbstractAction;
use App\Infrastructure\Http\OrderJsonPresenter;
use App\Infrastructure\Logging\OrderFlowLogger;
use App\Infrastructure\Security\ClientIdProvider;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(
    '/api/v1/investments/orders',
    name: 'api_v1_investments_orders_create',
    methods: ['POST'],
)]
final class CreateOrderAction extends AbstractAction
{
    private const string IDEMPOTENCY_HEADER = 'Idempotency-Key';

    public function __construct(
        LoggerInterface $logger,
        private readonly CreateOrderHandler $handler,
        private readonly ClientIdProvider $clientIdProvider,
        private readonly OrderFlowLogger $orderFlowLog,
    ) {
        parent::__construct($logger);
    }

    protected function handleRequest(Request $request): Response
    {
        $command = CreateOrderCommand::createFromRawValues(
            $this->getBody($request),
            (string) $request->headers->get(self::IDEMPOTENCY_HEADER, ''),
            $this->clientIdProvider->getClientId(),
        );

        $key = $command->idempotencyKey->toString();
        $this->orderFlowLog->event('request_received', 'http', $key, [
            'client_id' => $command->clientId->toString(),
        ]);

        $result = ($this->handler)($command);

        $this->orderFlowLog->event('http_accepted', 'http', $key, [
            'client_id' => $command->clientId->toString(),
            'order_id' => $result->order->id()->toString(),
            'duplicate' => $result->isDuplicate,
        ]);

        return $this->respondJson(
            OrderJsonPresenter::presentCreateAccepted($result->responsePayload),
            Response::HTTP_ACCEPTED,
        );
    }
}
