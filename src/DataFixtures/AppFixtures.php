<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Factory\CategoryFactory;
use App\Factory\HostFactory;
use App\Factory\SSHKeyFactory;
use App\Factory\UserFactory;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * @SuppressWarnings("ExcessiveMethodLength")
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // Create users
        $admin = UserFactory::new()
            ->asAdmin()
            ->create([
                'name' => 'Admin User',
                'email' => 'admin@keyroll.local',
                'password' => 'admin123',
            ]);

        $john = UserFactory::new()
            ->create([
                'name' => 'John Developer',
                'email' => 'john@keyroll.local',
                'password' => 'password123',
            ]);

        $jane = UserFactory::new()
            ->create([
                'name' => 'Jane DevOps',
                'email' => 'jane@keyroll.local',
                'password' => 'password123',
            ]);

        // Create categories
        $production = CategoryFactory::new()->production()->create();
        $staging = CategoryFactory::new()->staging()->create();
        $development = CategoryFactory::new()->development()->create();
        $webServers = CategoryFactory::new()->create(['name' => 'Web Servers']);
        $databases = CategoryFactory::new()->create(['name' => 'Database Servers']);
        $api = CategoryFactory::new()->create(['name' => 'API Servers']);

        // Create production web servers
        $webProd01 = HostFactory::new()
            ->asWebServer(1)
            ->connected()
            ->create([
                'hostname' => 'web01.prod.example.com',
                'username' => 'deployer',
            ]);
        $webProd01->addCategory($production);
        $webProd01->addCategory($webServers);

        $webProd02 = HostFactory::new()
            ->asWebServer(2)
            ->connected()
            ->create([
                'hostname' => 'web02.prod.example.com',
                'username' => 'deployer',
            ]);
        $webProd02->addCategory($production);
        $webProd02->addCategory($webServers);

        // Create production database servers
        $dbProd01 = HostFactory::new()
            ->asDatabaseServer(1)
            ->connected()
            ->create([
                'hostname' => 'db01.prod.example.com',
                'username' => 'admin',
            ]);
        $dbProd01->addCategory($production);
        $dbProd01->addCategory($databases);

        // Create staging servers
        $webStaging01 = HostFactory::new()
            ->asWebServer(1)
            ->connected()
            ->create([
                'name' => 'Web Server 01 (Staging)',
                'hostname' => 'web01.staging.example.com',
                'username' => 'deployer',
            ]);
        $webStaging01->addCategory($staging);
        $webStaging01->addCategory($webServers);

        $apiStaging01 = HostFactory::new()
            ->asApiServer(1)
            ->create([
                'name' => 'API Server 01 (Staging)',
                'hostname' => 'api01.staging.example.com',
                'username' => 'deployer',
            ]);
        $apiStaging01->addCategory($staging);
        $apiStaging01->addCategory($api);

        // Create development servers
        $devServer01 = HostFactory::new()
            ->create([
                'name' => 'Development Server 01',
                'hostname' => 'dev01.internal.example.com',
                'username' => 'developer',
                'port' => 22,
            ]);
        $devServer01->addCategory($development);

        $devServer02 = HostFactory::new()
            ->failed()
            ->create([
                'name' => 'Development Server 02',
                'hostname' => 'dev02.internal.example.com',
                'username' => 'developer',
                'port' => 2222,
            ]);
        $devServer02->addCategory($development);

        // Create additional random servers
        HostFactory::createMany(5, function () use ($production, $staging, $development) {
            $categories = [$production, $staging, $development];

            return [
                'categories' => [$categories[array_rand($categories)]],
            ];
        });

        // Assign users to categories
        $production->addUser($admin);
        $production->addUser($jane);

        $staging->addUser($admin);
        $staging->addUser($john);
        $staging->addUser($jane);

        $development->addUser($admin);
        $development->addUser($john);

        // Create SSH keys for users
        SSHKeyFactory::new()
            ->ed25519()
            ->create([
                'name' => 'Admin Master Key',
                'user' => $admin,
            ]);

        SSHKeyFactory::new()
            ->rsa()
            ->create([
                'name' => 'Admin Laptop Key',
                'user' => $admin,
            ]);

        SSHKeyFactory::new()
            ->ed25519()
            ->create([
                'name' => 'John Work Laptop',
                'user' => $john,
            ]);

        SSHKeyFactory::new()
            ->ed25519()
            ->create([
                'name' => 'Jane DevOps Key',
                'user' => $jane,
            ]);

        SSHKeyFactory::new()
            ->rsa()
            ->create([
                'name' => 'Jane Desktop Key',
                'user' => $jane,
            ]);

        // Create some random SSH keys
        SSHKeyFactory::createMany(3, function () use ($admin, $john, $jane) {
            $users = [$admin, $john, $jane];

            return [
                'user' => $users[array_rand($users)],
            ];
        });

        $manager->flush();
    }
}
