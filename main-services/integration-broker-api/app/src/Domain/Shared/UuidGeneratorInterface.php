<?php

declare(strict_types=1);

namespace App\Domain\Shared;

interface UuidGeneratorInterface
{
    public function generate(): string;
}
