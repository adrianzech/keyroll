<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Host;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class CategoryTest extends TestCase
{
    public function testCategoryCreation(): void
    {
        $category = new Category();
        $category->setName('Production Servers');

        $this->assertEquals('Production Servers', $category->getName());
    }

    public function testToString(): void
    {
        $category = new Category();
        $category->setName('Production Servers');

        $this->assertEquals('Production Servers', (string) $category);
    }

    public function testTimestamps(): void
    {
        $category = new Category();
        $category->updateTimestamps();

        $this->assertInstanceOf(\DateTimeImmutable::class, $category->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $category->getUpdatedAt());
    }

    public function testHostRelationship(): void
    {
        $category = new Category();
        $host = new Host();
        $host->setName('Web Server 01');
        $host->setHostname('web01.example.com');

        $category->addHost($host);

        $this->assertCount(1, $category->getHosts());
        $this->assertTrue($category->getHosts()->contains($host));

        // Remove host
        $category->removeHost($host);
        $this->assertCount(0, $category->getHosts());
    }

    public function testUserRelationship(): void
    {
        $category = new Category();
        $user = new User();
        $user->setName('John Doe');
        $user->setEmail('john@example.com');

        $category->addUser($user);

        $this->assertCount(1, $category->getUsers());
        $this->assertTrue($category->getUsers()->contains($user));

        // Remove user
        $category->removeUser($user);
        $this->assertCount(0, $category->getUsers());
    }

    public function testIdIsNullByDefault(): void
    {
        $category = new Category();
        $this->assertNull($category->getId());
    }
}
