<?php

declare(strict_types=1);

namespace App\Infrastructure\Broker;

use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Shared\Broker\BrokerCreateOrderResult;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Application\Shared\Broker\BrokerTokenProviderInterface;
use App\Application\Shared\MoneyStringToCents;
use App\Domain\Entity\Order;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpBrokerGateway implements BrokerGatewayInterface
{
    public function __construct(
        #[Autowire(service: 'broker.http_client')]
        private HttpClientInterface $httpClient,
        private BrokerTokenProviderInterface $tokenProvider,
    ) {
    }

    public function createOrder(Order $order): BrokerCreateOrderResult
    {
        return $this->sendCreateOrder($order, allowTokenRefresh: true);
    }

    private function sendCreateOrder(Order $order, bool $allowTokenRefresh): BrokerCreateOrderResult
    {
        $token = $this->tokenProvider->getAccessToken();

        try {
            $response = $this->httpClient->request('POST', '/v1/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Idempotency-Key' => $order->idempotencyKey()->toString(),
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => BrokerOrderRequestMapper::toBrokerPayload($order),
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw BrokerTransientException::withMessage($exception->getMessage());
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401 && $allowTokenRefresh) {
            $this->tokenProvider->invalidate($token);

            return $this->sendCreateOrder($order, allowTokenRefresh: false);
        }

        if ($statusCode === 202) {
            return $this->parseAcceptedResponse($response->toArray(false));
        }

        $body = $response->getContent(false);

        if (in_array($statusCode, [429, 502, 503], true)) {
            throw BrokerTransientException::withStatus($statusCode, $body);
        }

        if (in_array($statusCode, [400, 401, 403], true)) {
            throw BrokerFatalException::withStatus($statusCode, $body);
        }

        if ($statusCode >= 500) {
            throw BrokerTransientException::withStatus($statusCode, $body);
        }

        throw BrokerFatalException::withStatus($statusCode, $body);
    }

    /** @param array<string, mixed> $payload */
    private function parseAcceptedResponse(array $payload): BrokerCreateOrderResult
    {
        $brokerOrderId = $payload['order_id'] ?? null;
        $brokerStatus = $payload['status'] ?? null;
        $createdAt = $payload['created_at'] ?? null;

        if (!is_string($brokerOrderId) || $brokerOrderId === '') {
            throw BrokerFatalException::withStatus(202, 'Missing order_id in broker response.');
        }

        if (!is_string($brokerStatus) || $brokerStatus === '') {
            throw BrokerFatalException::withStatus(202, 'Missing status in broker response.');
        }

        if (!is_string($createdAt) || $createdAt === '') {
            throw BrokerFatalException::withStatus(202, 'Missing created_at in broker response.');
        }

        $expectedCommissionCents = null;
        if (isset($payload['expected_commission']) && is_string($payload['expected_commission'])) {
            $expectedCommissionCents = MoneyStringToCents::parse($payload['expected_commission']);
        }

        return new BrokerCreateOrderResult(
            brokerOrderId: $brokerOrderId,
            brokerStatus: $brokerStatus,
            brokerCreatedAt: new DateTimeImmutable($createdAt),
            expectedCommissionCents: $expectedCommissionCents,
        );
    }
}
