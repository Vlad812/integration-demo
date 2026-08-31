<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Application\Exception\AuthenticationRequiredException;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class ClientIdProvider
{
    public function __construct(
        private Security $security,
    ) {
    }

    public function getClientId(): string
    {
        $user = $this->security->getUser();

        if (!$user instanceof JwtClientUser) {
            throw AuthenticationRequiredException::missingJwtClient();
        }

        return $user->clientId();
    }
}
