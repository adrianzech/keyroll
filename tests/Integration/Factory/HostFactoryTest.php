<?php

declare(strict_types=1);

namespace App\Tests\Integration\Factory;

use App\Enum\HostConnectionStatus;
use App\Factory\HostFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HostFactoryTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testCreateHost(): void
    {
        $host = HostFactory::createOne();

        $this->assertNotNull($host->getName());
        $this->assertNotNull($host->getHostname());
        $this->assertNotNull($host->getPort());
        $this->assertNotNull($host->getUsername());
    }

    public function testCreateWebServer(): void
    {
        $webServer = HostFactory::new()->asWebServer(1)->create();

        $this->assertEquals('Web Server 01', $webServer->getName());
        $this->assertStringContainsString('web01', $webServer->getHostname());
        $this->assertEquals(22, $webServer->getPort());
        $this->assertEquals('deployer', $webServer->getUsername());
    }

    public function testCreateDatabaseServer(): void
    {
        $dbServer = HostFactory::new()->asDatabaseServer(2)->create();

        $this->assertEquals('Database Server 02', $dbServer->getName());
        $this->assertStringContainsString('db02', $dbServer->getHostname());
        $this->assertEquals('admin', $dbServer->getUsername());
    }

    public function testConnectionStatusModifiers(): void
    {
        $connectedHost = HostFactory::new()->connected()->create();
        $this->assertEquals(HostConnectionStatus::SUCCESSFUL, $connectedHost->getConnectionStatus());

        $failedHost = HostFactory::new()->failed()->create();
        $this->assertEquals(HostConnectionStatus::FAILED, $failedHost->getConnectionStatus());

        $checkingHost = HostFactory::new()->checking()->create();
        $this->assertEquals(HostConnectionStatus::CHECKING, $checkingHost->getConnectionStatus());
    }

    public function testCustomPort(): void
    {
        $host = HostFactory::new()->withCustomPort(2222)->create();

        $this->assertEquals(2222, $host->getPort());
    }
}
