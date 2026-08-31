<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Shared\UuidGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class SymfonyUuidGenerator implements UuidGeneratorInterface
{
    public function generate(): string
    {
        return Uuid::v4()->toRfc4122();
    }
}
