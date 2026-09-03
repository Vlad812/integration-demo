<?php

declare(strict_types=1);

namespace App\Infrastructure\Broker;

use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Shared\Broker\BrokerCreateOrderResult;
use App\Application\Shared\Broker\BrokerGatewayInterface;
use App\Application\Shared\Broker\BrokerOrderSnapshot;
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

    public function getOrder(string $brokerOrderId): BrokerOrderSnapshot
    {
        return $this->sendGetOrder($brokerOrderId, allowTokenRefresh: true);
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

    private function sendGetOrder(string $brokerOrderId, bool $allowTokenRefresh): BrokerOrderSnapshot
    {
        $token = $this->tokenProvider->getAccessToken();

        try {
            $response = $this->httpClient->request('GET', '/v1/orders/' . rawurlencode($brokerOrderId), [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ],
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw BrokerTransientException::withMessage($exception->getMessage());
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401 && $allowTokenRefresh) {
            $this->tokenProvider->invalidate($token);

            return $this->sendGetOrder($brokerOrderId, allowTokenRefresh: false);
        }

        if ($statusCode === 200) {
            return $this->parseOrderSnapshot($response->toArray(false));
        }

        $body = $response->getContent(false);

        if ($statusCode === 404 || in_array($statusCode, [429, 502, 503], true)) {
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

    /** @param array<string, mixed> $payload */
    private function parseOrderSnapshot(array $payload): BrokerOrderSnapshot
    {
        $brokerOrderId = $payload['order_id'] ?? null;
        $brokerStatus = $payload['status'] ?? null;
        $ticker = $payload['instrument_ticker'] ?? null;
        $side = $payload['side'] ?? null;
        $requestedQuantity = $payload['requested_quantity'] ?? null;
        $executedQuantity = $payload['executed_quantity'] ?? null;
        $currency = $payload['currency'] ?? null;
        $updatedAt = $payload['updated_at'] ?? null;

        if (!is_string($brokerOrderId) || $brokerOrderId === '') {
            throw BrokerFatalException::withStatus(200, 'Missing order_id in broker response.');
        }

        if (!is_string($brokerStatus) || $brokerStatus === '') {
            throw BrokerFatalException::withStatus(200, 'Missing status in broker response.');
        }

        if (!is_string($ticker) || $ticker === '') {
            throw BrokerFatalException::withStatus(200, 'Missing instrument_ticker in broker response.');
        }

        if (!is_string($side) || $side === '') {
            throw BrokerFatalException::withStatus(200, 'Missing side in broker response.');
        }

        if (!is_int($requestedQuantity) && !is_numeric($requestedQuantity)) {
            throw BrokerFatalException::withStatus(200, 'Missing requested_quantity in broker response.');
        }

        if (!is_int($executedQuantity) && !is_numeric($executedQuantity)) {
            throw BrokerFatalException::withStatus(200, 'Missing executed_quantity in broker response.');
        }

        if (!is_string($currency) || $currency === '') {
            throw BrokerFatalException::withStatus(200, 'Missing currency in broker response.');
        }

        if (!is_string($updatedAt) || $updatedAt === '') {
            throw BrokerFatalException::withStatus(200, 'Missing updated_at in broker response.');
        }

        $avgPriceCents = null;
        if (isset($payload['average_execution_price']) && is_string($payload['average_execution_price'])) {
            $avgPriceCents = MoneyStringToCents::parse($payload['average_execution_price']);
        }

        $totalValueCents = null;
        if (isset($payload['executed_value']) && is_string($payload['executed_value'])) {
            $totalValueCents = MoneyStringToCents::parse($payload['executed_value']);
        }

        return new BrokerOrderSnapshot(
            brokerOrderId: $brokerOrderId,
            brokerStatus: $brokerStatus,
            ticker: $ticker,
            side: $side,
            requestedQuantity: (int) $requestedQuantity,
            executedQuantity: (int) $executedQuantity,
            avgPriceCents: $avgPriceCents,
            totalValueCents: $totalValueCents,
            currency: $currency,
            updatedAt: new DateTimeImmutable($updatedAt),
        );
    }
}
