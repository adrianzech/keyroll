<?php

declare(strict_types=1);

namespace App\Tests\Integration\Factory;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class UserFactoryTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testCreateUser(): void
    {
        $user = UserFactory::createOne();

        $this->assertNotNull($user->getEmail());
        $this->assertNotNull($user->getName());
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testCreateAdmin(): void
    {
        $admin = UserFactory::new()->asAdmin()->create();

        $this->assertContains('ROLE_ADMIN', $admin->getRoles());
    }

    public function testPasswordIsHashed(): void
    {
        $user = UserFactory::new()->withPlainPassword('test123')->create();

        // Password should be hashed (starts with $ for bcrypt/argon2)
        $this->assertStringStartsWith('$', $user->getPassword());
    }

    public function testCreateMultipleUsers(): void
    {
        $users = UserFactory::createMany(5);

        $this->assertCount(5, $users);

        // All emails should be unique
        $emails = array_map(fn ($user) => $user->getEmail(), $users);
        $this->assertCount(5, array_unique($emails));
    }
}
