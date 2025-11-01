<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        $entityManager = $kernel->getContainer()
            ->get('doctrine')
            ->getManager();

        $repository = $entityManager->getRepository(User::class);
        assert($repository instanceof UserRepository);
        $this->repository = $repository;
    }

    public function testFindByEmail(): void
    {
        $user = new User();
        $user->setName('Test User');
        $user->setEmail('test@example.com');
        $user->setPassword('hashed_password');

        $entityManager = $this->repository->createQueryBuilder('u')
            ->getEntityManager();

        $entityManager->persist($user);
        $entityManager->flush();

        $foundUser = $this->repository->findOneBy(['email' => 'test@example.com']);

        $this->assertNotNull($foundUser);
        $this->assertEquals('test@example.com', $foundUser->getEmail());
        $this->assertEquals('Test User', $foundUser->getName());

        // Cleanup
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function testUserPersistence(): void
    {
        $user = new User();
        $user->setName('Persistent User');
        $user->setEmail('persistent@example.com');
        $user->setPassword('hashed_password');
        $user->setRoles(['ROLE_ADMIN']);

        $entityManager = $this->repository->createQueryBuilder('u')
            ->getEntityManager();

        $entityManager->persist($user);
        $entityManager->flush();

        $userId = $user->getId();
        $entityManager->clear();

        $foundUser = $this->repository->find($userId);

        $this->assertNotNull($foundUser);
        $this->assertContains('ROLE_ADMIN', $foundUser->getRoles());

        // Cleanup
        $entityManager->remove($foundUser);
        $entityManager->flush();
    }
}
