<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Category;
use App\Entity\Host;
use App\Enum\HostConnectionStatus;
use PHPUnit\Framework\TestCase;

class HostTest extends TestCase
{
    public function testHostCreation(): void
    {
        $host = new Host();
        $host->setName('Web Server 01');
        $host->setHostname('web01.prod.example.com');
        $host->setPort(22);
        $host->setUsername('deployer');

        $this->assertEquals('Web Server 01', $host->getName());
        $this->assertEquals('web01.prod.example.com', $host->getHostname());
        $this->assertEquals(22, $host->getPort());
        $this->assertEquals('deployer', $host->getUsername());
    }

    public function testDefaultConnectionStatus(): void
    {
        $host = new Host();

        $this->assertEquals(HostConnectionStatus::UNKNOWN, $host->getConnectionStatus());
    }

    public function testSetConnectionStatus(): void
    {
        $host = new Host();
        $host->setConnectionStatus(HostConnectionStatus::SUCCESSFUL);

        $this->assertEquals(HostConnectionStatus::SUCCESSFUL, $host->getConnectionStatus());
    }

    public function testTimestamps(): void
    {
        $host = new Host();
        $host->updateTimestamps();

        $this->assertInstanceOf(\DateTimeImmutable::class, $host->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $host->getUpdatedAt());
    }

    public function testUpdatedAtIsRefreshed(): void
    {
        $host = new Host();
        $host->updateTimestamps();

        $firstUpdatedAt = $host->getUpdatedAt();

        // Wait a tiny bit to ensure timestamp difference
        usleep(1000);

        $host->updateTimestamps();
        $secondUpdatedAt = $host->getUpdatedAt();

        $this->assertNotEquals($firstUpdatedAt, $secondUpdatedAt);
    }

    public function testCategoryRelationship(): void
    {
        $host = new Host();
        $category = new Category();
        $category->setName('Production');

        $host->addCategory($category);

        $this->assertCount(1, $host->getCategories());
        $this->assertTrue($host->getCategories()->contains($category));

        // Remove category
        $host->removeCategory($category);
        $this->assertCount(0, $host->getCategories());
    }

    public function testIdIsNullByDefault(): void
    {
        $host = new Host();
        $this->assertNull($host->getId());
    }

    public function testDefaultPort(): void
    {
        $host = new Host();
        // Port should have default value from constructor or be null
        $this->assertTrue($host->getPort() === 22 || $host->getPort() === null);
    }
}
