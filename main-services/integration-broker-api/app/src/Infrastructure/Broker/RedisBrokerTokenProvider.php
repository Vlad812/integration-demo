<?php

declare(strict_types=1);

namespace App\Infrastructure\Broker;

use App\Application\Exception\BrokerFatalException;
use App\Application\Exception\BrokerTransientException;
use App\Application\Shared\Broker\BrokerTokenProviderInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Lock\LockFactory;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class RedisBrokerTokenProvider implements BrokerTokenProviderInterface
{
    private const string CACHE_KEY = 'broker_access_token';
    private const string LOCK_KEY = 'broker-oauth-token';
    private const int TTL_SAFETY_SECONDS = 60;

    public function __construct(
        #[Autowire(service: 'cache.broker_token')]
        private CacheItemPoolInterface $cache,
        #[Autowire(service: 'broker.oauth_http_client')]
        private HttpClientInterface $oauthHttpClient,
        private LockFactory $lockFactory,
        #[Autowire('%env(BROKER_OAUTH_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(BROKER_OAUTH_CLIENT_SECRET)%')]
        private string $clientSecret,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getAccessToken(): string
    {
        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            $token = $item->get();

            if (is_string($token) && $token !== '') {
                return $token;
            }
        }

        $lock = $this->lockFactory->createLock(self::LOCK_KEY, ttl: 30.0);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem(self::CACHE_KEY);

            if ($item->isHit()) {
                $token = $item->get();

                if (is_string($token) && $token !== '') {
                    return $token;
                }
            }

            $tokenData = $this->fetchTokenFromBroker();
            $accessToken = $tokenData['access_token'];
            $expiresIn = $tokenData['expires_in'];

            $item->set($accessToken);
            $item->expiresAfter(max(1, $expiresIn - self::TTL_SAFETY_SECONDS));
            $this->cache->save($item);

            return $accessToken;
        } finally {
            $lock->release();
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidate(string $oldToken): void
    {
        $lock = $this->lockFactory->createLock(self::LOCK_KEY, ttl: 10.0);
        $lock->acquire(true);

        try {
            $item = $this->cache->getItem(self::CACHE_KEY);

            if ($item->isHit() && $item->get() === $oldToken) {
                $this->cache->deleteItem(self::CACHE_KEY);
            }
        } finally {
            $lock->release();
        }
    }

    /** @return array{access_token: string, expires_in: int}
     * @throws TransportExceptionInterface
     */
    private function fetchTokenFromBroker(): array
    {
        try {
            $response = $this->oauthHttpClient->request('POST', '', [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept' => 'application/json',
                ],
                'body' => http_build_query([
                    'grant_type' => 'client_credentials',
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                ]),
            ]);
        } catch (TransportExceptionInterface $exception) {
            throw BrokerTransientException::withMessage($exception->getMessage());
        }

        $statusCode = $response->getStatusCode();

        if ($statusCode !== 200) {
            throw BrokerFatalException::withStatus($statusCode, $response->getContent(false));
        }

        $payload = $response->toArray(false);
        $accessToken = $payload['access_token'] ?? null;
        $expiresIn = $payload['expires_in'] ?? null;

        if (!is_string($accessToken) || $accessToken === '') {
            throw BrokerFatalException::withStatus(200, 'Missing access_token in OAuth response.');
        }

        if (!is_int($expiresIn) && !is_string($expiresIn)) {
            throw BrokerFatalException::withStatus(200, 'Missing expires_in in OAuth response.');
        }

        return [
            'access_token' => $accessToken,
            'expires_in' => (int) $expiresIn,
        ];
    }
}
