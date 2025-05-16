<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Finds users by email, optionally excluding certain IDs.
     *
     * @param string $query       the search query for the user email
     * @param int[]  $excludedIds an array of user IDs to exclude from the results
     *
     * @return User[] returns an array of User objects
     */
    public function findByEmailLike(string $query, array $excludedIds = []): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('LOWER(u.email) LIKE LOWER(:query)')
            ->setParameter('query', '%' . mb_strtolower($query) . '%')
            ->orderBy('u.email', 'ASC')
            ->setMaxResults(10);

        if (!empty($excludedIds)) {
            $qb->andWhere('u.id NOT IN (:excludedIds)')
                ->setParameter('excludedIds', $excludedIds);
        }

        /** @var User[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }

    /**
     * Finds all User entities with sorting.
     *
     * @param string $sortBy        The property to sort by (e.g., 'name', 'email').
     * @param string $sortDirection 'ASC' or 'DESC'
     *
     * @return User[] returns an array of User objects
     */
    public function findWithSorting(string $sortBy, string $sortDirection): array
    {
        $qb = $this->createQueryBuilder('u');
        $qb->orderBy('u.' . $sortBy, $sortDirection);

        /** @var User[] $result */
        $result = $qb->getQuery()->getResult();

        return $result;
    }
}
