<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Host;
use App\Enum\HostConnectionStatus;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<Host>
 */
final class HostFactory extends PersistentProxyObjectFactory
{
    private const SERVER_TYPES = ['web', 'db', 'api', 'cache', 'mail', 'app', 'worker', 'proxy'];
    private const ENVIRONMENTS = ['prod', 'staging', 'dev', 'test'];
    private const USERNAMES = ['deployer', 'admin', 'ubuntu', 'root', 'docker', 'ansible'];

    public static function class(): string
    {
        return Host::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        $serverType = self::faker()->randomElement(self::SERVER_TYPES);
        $environment = self::faker()->randomElement(self::ENVIRONMENTS);
        $number = self::faker()->numberBetween(1, 10);
        $domain = self::faker()->domainName();

        return [
            'name' => ucfirst($serverType) . ' Server ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'hostname' => sprintf('%s%02d.%s.%s', $serverType, $number, $environment, $domain),
            'port' => self::faker()->randomElement([22, 2222, 22022]),
            'username' => self::faker()->randomElement(self::USERNAMES),
            'connectionStatus' => HostConnectionStatus::UNKNOWN,
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public function asWebServer(int $number = 1): self
    {
        return $this->with([
            'name' => 'Web Server ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'hostname' => sprintf('web%02d.prod.%s', $number, self::faker()->domainName()),
            'port' => 22,
            'username' => 'deployer',
        ]);
    }

    public function asDatabaseServer(int $number = 1): self
    {
        return $this->with([
            'name' => 'Database Server ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'hostname' => sprintf('db%02d.prod.%s', $number, self::faker()->domainName()),
            'port' => 22,
            'username' => 'admin',
        ]);
    }

    public function asApiServer(int $number = 1): self
    {
        return $this->with([
            'name' => 'API Server ' . str_pad((string) $number, 2, '0', STR_PAD_LEFT),
            'hostname' => sprintf('api%02d.prod.%s', $number, self::faker()->domainName()),
            'port' => 22,
            'username' => 'deployer',
        ]);
    }

    public function withCustomPort(int $port): self
    {
        return $this->with(['port' => $port]);
    }

    public function connected(): self
    {
        return $this->with(['connectionStatus' => HostConnectionStatus::SUCCESSFUL]);
    }

    public function failed(): self
    {
        return $this->with(['connectionStatus' => HostConnectionStatus::FAILED]);
    }

    public function checking(): self
    {
        return $this->with(['connectionStatus' => HostConnectionStatus::CHECKING]);
    }
}
