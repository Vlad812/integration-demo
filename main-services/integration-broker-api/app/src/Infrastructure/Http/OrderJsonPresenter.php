<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

final class OrderJsonPresenter
{
    /** @param array<string, mixed> $payload */
    public static function presentCreateAccepted(array $payload): array
    {
        return $payload;
    }
}
