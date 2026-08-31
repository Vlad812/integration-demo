<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\User\UserInterface;

final readonly class JwtClientUser implements UserInterface
{
    public function __construct(
        private string $clientId,
    ) {
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->clientId;
    }
}
