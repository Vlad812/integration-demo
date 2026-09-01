<?php

declare(strict_types=1);

namespace App\Application\Exception;

use RuntimeException;

final class BrokerFatalException extends RuntimeException
{
    public static function withStatus(int $statusCode, string $message): self
    {
        return new self(sprintf('Broker fatal error (HTTP %d): %s', $statusCode, $message));
    }
}
