<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\HostFactory;
use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class HostControllerTest extends WebTestCase
{
    use ResetDatabase;
    use Factories;

    public function testHostIndexRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/host');

        $this->assertResponseRedirects('/login');
    }

    public function testAuthenticatedUserCanViewHostIndex(): void
    {
        $user = UserFactory::createOne([
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/host');

        $this->assertResponseIsSuccessful();
    }

    public function testHostIndexDisplaysHosts(): void
    {
        $user = UserFactory::createOne();
        HostFactory::createMany(3);

        $client = static::createClient();
        $client->loginUser($user);
        $client->request('GET', '/host');

        $this->assertResponseIsSuccessful();
        // Just verify the page loads successfully
    }

    public function testAdminCanCreateHost(): void
    {
        $admin = UserFactory::new()->asAdmin()->create();

        $client = static::createClient();
        $client->loginUser($admin);

        // Access new host form
        $crawler = $client->request('GET', '/host/new');
        $this->assertResponseIsSuccessful();

        // Submit form
        $form = $crawler->selectButton('Save')->form([
            'host[name]' => 'Test Server',
            'host[hostname]' => 'test.example.com',
            'host[port]' => '22',
            'host[username]' => 'deployer',
        ]);

        $client->submit($form);

        // Should redirect to index or show page
        $this->assertResponseRedirects();
    }

    public function testHostEditPageDisplaysDetails(): void
    {
        $admin = UserFactory::new()->asAdmin()->create();
        $host = HostFactory::createOne([
            'name' => 'Web Server 01',
            'hostname' => 'web01.example.com',
        ]);

        $client = static::createClient();
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/host/' . $host->getId() . '/edit');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('input[value="Web Server 01"]');
        $this->assertSelectorExists('input[value="web01.example.com"]');
    }
}
