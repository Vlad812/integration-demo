<?php

declare(strict_types=1);

namespace App\Infrastructure\Logging;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final readonly class OrderFlowLogger
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.order_flow')]
        private LoggerInterface $logger,
    ) {
    }

    /** @param array<string, mixed> $context */
    public function event(string $event, string $stage, string $idempotencyKey, array $context = []): void
    {
        $this->logger->info($event, [
            'stage' => $stage,
            'idempotency_key' => $idempotencyKey,
            ...$context,
        ]);
    }
}
