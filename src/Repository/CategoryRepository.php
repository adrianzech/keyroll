<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Finds all Category entities with sorting.
     *
     * @param string $sortBy        The property to sort by.
     *                              Can be a direct property like 'name', 'createdAt',
     *                              or special keys like 'hostsCount' or 'usersCount'.
     * @param string $sortDirection 'ASC' or 'DESC'
     *
     * @return Category[] returns an array of Category objects
     */
    public function findWithSorting(string $sortBy, string $sortDirection): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($sortBy === 'hostsCount') {
            $qb->addSelect('SIZE(c.hosts) AS HIDDEN hosts_count_val')
                ->orderBy('hosts_count_val', $sortDirection);

            return $qb->getQuery()->getResult();
        }

        if ($sortBy === 'usersCount') {
            $qb->addSelect('SIZE(c.users) AS HIDDEN users_count_val')
                ->orderBy('users_count_val', $sortDirection);

            return $qb->getQuery()->getResult();
        }

        $qb->orderBy('c.' . $sortBy, $sortDirection);

        return $qb->getQuery()->getResult();
    }
}
