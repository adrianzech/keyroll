<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SSHKey;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SSHKey>
 */
class SSHKeyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SSHKey::class);
    }

    /**
     * Finds SSH keys with filtering and sorting.
     *
     * @param User|null $user          Optional user to filter by. If null, fetches for all users (admin view).
     * @param string    $sortBy        The property to sort by (e.g., 'name', 'createdAt', 'user.email').
     * @param string    $sortDirection 'ASC' or 'DESC'
     *
     * @return SSHKey[]
     */
    public function findWithSorting(?User $user, string $sortBy, string $sortDirection): array
    {
        $qb = $this->createQueryBuilder('k');

        if ($user !== null) {
            $qb->andWhere('k.user = :user')
                ->setParameter('user', $user);
        }

        if (str_contains($sortBy, '.')) {
            [$relation, $field] = explode('.', $sortBy, 2);
            if ($relation === 'user') {
                $qb->leftJoin('k.user', 'u_alias');
                $qb->orderBy('u_alias.' . $field, $sortDirection);

                return $qb->getQuery()->getResult();
            }
        }

        $qb->orderBy('k.' . $sortBy, $sortDirection);

        return $qb->getQuery()->getResult();
    }
}
