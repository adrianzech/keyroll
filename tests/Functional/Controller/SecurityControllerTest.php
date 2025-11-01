<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class SecurityControllerTest extends WebTestCase
{
    use ResetDatabase;
    use Factories;

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="email"]');
        $this->assertSelectorExists('input[name="password"]');
    }

    public function testLoginWithValidCredentials(): void
    {
        // Create a test user
        UserFactory::createOne([
            'email' => 'test@example.com',
            'password' => 'test123',
            'name' => 'Test User',
        ]);

        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        // Submit login form - find button by type=submit
        $form = $crawler->filter('form')->form([
            'email' => 'test@example.com',
            'password' => 'test123',
        ]);

        $client->submit($form);

        // Should redirect to homepage after successful login
        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $form = $crawler->filter('form')->form([
            'email' => 'invalid@example.com',
            'password' => 'wrongpassword',
        ]);

        $client->submit($form);

        // Should redirect back to login with error
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-danger');
    }

    public function testRegisterPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
    }

    public function testLogoutRedirectsToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/logout');

        $this->assertResponseRedirects('/login');
    }
}
