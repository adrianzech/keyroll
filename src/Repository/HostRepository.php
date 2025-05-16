<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Host;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Host>
 */
class HostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Host::class);
    }

    /**
     * Finds hosts by name or hostname, optionally excluding certain IDs.
     *
     * @param string $query       the search query for the host name/hostname
     * @param int[]  $excludedIds an array of host IDs to exclude from the results
     *
     * @return Host[] returns an array of Host objects
     */
    public function findByNameOrHostnameLike(string $query, array $excludedIds = []): array
    {
        $qb = $this->createQueryBuilder('h')
            ->where('LOWER(h.name) LIKE LOWER(:query) OR LOWER(h.hostname) LIKE LOWER(:query)')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('h.name', 'ASC')
            ->setMaxResults(10);

        if (!empty($excludedIds)) {
            $qb->andWhere('h.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $excludedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds all Host entities with sorting.
     *
     * @param string $sortBy        The property to sort by (e.g., 'name', 'hostname', 'createdAt').
     * @param string $sortDirection 'ASC' or 'DESC'
     *
     * @return Host[] returns an array of Host objects
     */
    public function findWithSorting(string $sortBy, string $sortDirection): array
    {
        $qb = $this->createQueryBuilder('h');

        $qb->orderBy('h.' . $sortBy, $sortDirection);

        return $qb->getQuery()->getResult();
    }
}
