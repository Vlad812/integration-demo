<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use DomainException;

final class ResourceNotFoundException extends DomainException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Resource with id "%s" was not found.', $id));
    }
}
