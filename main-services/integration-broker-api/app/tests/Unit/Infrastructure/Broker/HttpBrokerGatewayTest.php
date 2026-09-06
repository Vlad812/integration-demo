<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Broker;

use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Shared\Broker\BrokerTokenProviderInterface;
use App\Infrastructure\Broker\HttpBrokerGateway;
use App\Tests\Unit\Support\OrderFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpBrokerGatewayTest extends TestCase
{
    private BrokerTokenProviderInterface&MockObject $tokenProvider;

    protected function setUp(): void
    {
        $this->tokenProvider = $this->createMock(BrokerTokenProviderInterface::class);
        $this->tokenProvider->method('getAccessToken')->willReturn('token-1');
    }

    public function testCreateOrderSuccess202(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'order_id' => 'ord_broker_1',
                'status' => 'PENDING_ROUTING',
                'created_at' => '2026-01-15T12:00:00Z',
                'expected_commission' => '1.50',
            ], JSON_THROW_ON_ERROR), ['http_code' => 202]),
        ]);

        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);
        $result = $gateway->createOrder(OrderFactory::createNew());

        self::assertSame('ord_broker_1', $result->brokerOrderId);
        self::assertSame('PENDING_ROUTING', $result->brokerStatus);
        self::assertSame(150, $result->expectedCommissionCents);
    }

    public function testCreateOrderTransientOn429(): void
    {
        $client = new MockHttpClient([
            new MockResponse('busy', ['http_code' => 429]),
        ]);
        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);

        $this->expectException(BrokerTransientException::class);
        $gateway->createOrder(OrderFactory::createNew());
    }

    public function testCreateOrderTransientOn5xx(): void
    {
        $client = new MockHttpClient([
            new MockResponse('error', ['http_code' => 503]),
        ]);
        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);

        $this->expectException(BrokerTransientException::class);
        $gateway->createOrder(OrderFactory::createNew());
    }

    public function testCreateOrderFatalOn400(): void
    {
        $client = new MockHttpClient([
            new MockResponse('bad', ['http_code' => 400]),
        ]);
        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);

        $this->expectException(BrokerFatalException::class);
        $gateway->createOrder(OrderFactory::createNew());
    }

    public function testCreateOrderRefreshesTokenOnceOn401(): void
    {
        $this->tokenProvider = $this->createMock(BrokerTokenProviderInterface::class);
        $this->tokenProvider->method('getAccessToken')->willReturnOnConsecutiveCalls('old-token', 'new-token');
        $this->tokenProvider->expects(self::once())->method('invalidate')->with('old-token');

        $client = new MockHttpClient([
            new MockResponse('unauthorized', ['http_code' => 401]),
            new MockResponse(json_encode([
                'order_id' => 'ord_broker_1',
                'status' => 'PENDING_ROUTING',
                'created_at' => '2026-01-15T12:00:00Z',
            ], JSON_THROW_ON_ERROR), ['http_code' => 202]),
        ]);

        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);
        $result = $gateway->createOrder(OrderFactory::createNew());

        self::assertSame('ord_broker_1', $result->brokerOrderId);
        self::assertSame(2, $client->getRequestsCount());
    }

    public function testGetOrderSuccess200(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'order_id' => 'ord_broker_1',
                'status' => 'PARTIALLY_FILLED',
                'instrument_ticker' => 'AAPL',
                'side' => 'BUY',
                'requested_quantity' => 10,
                'executed_quantity' => 4,
                'average_execution_price' => '145.20',
                'executed_value' => '580.80',
                'currency' => 'USD',
                'updated_at' => '2026-01-15T13:00:00Z',
            ], JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);
        $snapshot = $gateway->getOrder('ord_broker_1');

        self::assertSame('ord_broker_1', $snapshot->brokerOrderId);
        self::assertSame('PARTIALLY_FILLED', $snapshot->brokerStatus);
        self::assertSame(4, $snapshot->executedQuantity);
        self::assertSame(14520, $snapshot->avgPriceCents);
        self::assertSame(58080, $snapshot->totalValueCents);
    }

    public function testGetOrderTransientOn404(): void
    {
        $client = new MockHttpClient([
            new MockResponse('missing', ['http_code' => 404]),
        ]);
        $gateway = new HttpBrokerGateway($client, $this->tokenProvider);

        $this->expectException(BrokerTransientException::class);
        $gateway->getOrder('ord_missing');
    }
}
