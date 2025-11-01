<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\SSHKey;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testUserCreation(): void
    {
        $user = new User();
        $user->setName('John Doe');
        $user->setEmail('john@example.com');
        $user->setPassword('hashed_password');

        $this->assertEquals('John Doe', $user->getName());
        $this->assertEquals('john@example.com', $user->getEmail());
        $this->assertEquals('hashed_password', $user->getPassword());
        $this->assertEquals('john@example.com', $user->getUserIdentifier());
    }

    public function testDefaultRoles(): void
    {
        $user = new User();

        // User should always have at least ROLE_USER
        $this->assertContains('ROLE_USER', $user->getRoles());
    }

    public function testSetRoles(): void
    {
        $user = new User();
        $user->setRoles(['ROLE_ADMIN']);

        $roles = $user->getRoles();
        $this->assertContains('ROLE_USER', $roles);
        $this->assertContains('ROLE_ADMIN', $roles);
    }

    public function testGetPrimaryRole(): void
    {
        $user = new User();

        // Default user
        $this->assertEquals('ROLE_USER', $user->getPrimaryRole());

        // Admin user
        $user->setRoles(['ROLE_ADMIN']);
        $this->assertEquals('ROLE_ADMIN', $user->getPrimaryRole());
    }

    public function testEraseCredentials(): void
    {
        $this->expectNotToPerformAssertions();

        $user = new User();
        $user->eraseCredentials();

        // This method should not throw an exception
    }

    public function testCategoryRelationship(): void
    {
        $user = new User();
        $category = new Category();
        $category->setName('Production');

        $user->addCategory($category);

        $this->assertCount(1, $user->getCategories());
        $this->assertTrue($user->getCategories()->contains($category));

        // Remove category
        $user->removeCategory($category);
        $this->assertCount(0, $user->getCategories());
    }

    public function testSshKeyRelationship(): void
    {
        $user = new User();
        $sshKey = new SSHKey();
        $sshKey->setName('Test Key');
        $sshKey->setPublicKey('ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQ...');

        $user->addSshKey($sshKey);

        $this->assertCount(1, $user->getSshKeys());
        $this->assertTrue($user->getSshKeys()->contains($sshKey));
        $this->assertEquals($user, $sshKey->getUser());

        // Remove SSH key
        $user->removeSshKey($sshKey);
        $this->assertCount(0, $user->getSshKeys());
        $this->assertNull($sshKey->getUser());
    }

    public function testIdIsNullByDefault(): void
    {
        $user = new User();
        $this->assertNull($user->getId());
    }
}
