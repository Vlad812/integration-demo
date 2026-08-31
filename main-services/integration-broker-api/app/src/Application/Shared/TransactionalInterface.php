<?php

declare(strict_types=1);

namespace App\Application\Shared;

interface TransactionalInterface
{
    public function execute(callable $operation): mixed;
}
