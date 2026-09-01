<?php

declare(strict_types=1);

namespace App\Application\Shared\Broker;

interface BrokerTokenProviderInterface
{
    public function getAccessToken(): string;

    public function invalidate(string $oldToken): void;
}
