<?php

declare(strict_types=1);

namespace App\Application\Exception;

use RuntimeException;

final class BrokerTransientException extends RuntimeException
{
    public static function withStatus(int $statusCode, string $message): self
    {
        return new self(sprintf('Broker transient error (HTTP %d): %s', $statusCode, $message));
    }

    public static function withMessage(string $message): self
    {
        return new self(sprintf('Broker transient error: %s', $message));
    }
}
