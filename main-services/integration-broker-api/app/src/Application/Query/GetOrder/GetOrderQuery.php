<?php

declare(strict_types=1);

namespace App\Application\Query\GetOrder;

final readonly class GetOrderQuery
{
    public function __construct(
        public string $publicId,
        public string $clientId,
    ) {
    }
}
