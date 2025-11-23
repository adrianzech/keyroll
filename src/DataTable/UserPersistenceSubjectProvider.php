<?php

declare(strict_types=1);

namespace App\DataTable;

use Kreyu\Bundle\DataTableBundle\Persistence\PersistenceSubjectAggregate;
use Kreyu\Bundle\DataTableBundle\Persistence\PersistenceSubjectNotFoundException;
use Kreyu\Bundle\DataTableBundle\Persistence\PersistenceSubjectProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserPersistenceSubjectProvider implements PersistenceSubjectProviderInterface
{
    public function __construct(private TokenStorageInterface $tokenStorage)
    {
    }

    public function provide(): PersistenceSubjectAggregate
    {
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$user instanceof UserInterface) {
            throw PersistenceSubjectNotFoundException::createForProvider($this);
        }

        $identifier = $this->buildIdentifier($user);

        return new PersistenceSubjectAggregate($identifier, $user);
    }

    private function buildIdentifier(UserInterface $user): string
    {
        // Prefer a stable numeric/UUID id when available to avoid cache tag restrictions.
        if (method_exists($user, 'getId') && null !== $user->getId()) {
            return 'user_' . $user->getId();
        }

        // Fallback to the user identifier, sanitized for cache tag safety.
        $raw = $user->getUserIdentifier();

        return preg_replace('/[^A-Za-z0-9_.-]/', '_', $raw) ?: 'user_unknown';
    }
}
