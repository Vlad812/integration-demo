<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/** @implements UserProviderInterface<JwtClientUser> */
final class JwtClientUserProvider implements UserProviderInterface
{
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $clientId = trim($identifier);

        if ($clientId === '') {
            throw new UserNotFoundException('JWT client_id is empty.');
        }

        return new JwtClientUser($clientId);
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof JwtClientUser) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return $class === JwtClientUser::class || is_subclass_of($class, JwtClientUser::class);
    }
}
