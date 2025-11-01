<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\SSHKey;
use App\Entity\User;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<SSHKey>
 *
 * @SuppressWarnings("PHPMD.StaticAccess")
 */
final class SSHKeyFactory extends PersistentObjectFactory
{
    // Valid SSH public key examples for testing (these are example keys, not real private keys)
    private const SSH_KEYS = [
        'ssh-ed25519' => [
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIOMqqnkVzrm0SdG6UOoqKLsabgH5C9okWi0dh2l9GKJl user@example.com',
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIFN9IlZcKmGJGDeMp3V4M2HVMdD4LANAHMzUxRzK7E9p deploy@server',
            'ssh-ed25519 AAAAC3NzaC1lZDI1NTE5AAAAIKcCc2WwvNqVlVYlM1sNPTBQJBs5J2hk2KCNMi8N8xYj admin@workstation',
        ],
        'ssh-rsa' => [
            'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABgQC7Z3Vm8h9LQUn4ZxPZVYhYJO3l2Jh0sH5NLvJk7B9iFhT5xN7d4K5L6M7N8O9P0Q1R2S3T4U5V6W7X8Y9Z0A1B2C3D4E5F6G7H8I9J0K1L2M3N4O5P6Q7R8S9T0U1V2W3X4Y5Z6A7B8C9D0E1F2G3H4I5J6K7L8M9N0O1P2Q3R4S5T6U7V8W9X0Y1Z2 user@laptop',
            'ssh-rsa AAAAB3NzaC1yc2EAAAADAQABAAABAQC9z8Xm5h7LPUk2ZvPWVXgYHO1l0Jg9sG4NKuJj6B8hFgT4xM6d3K4L5M6N7O8P9Q0R1S2T3U4V5W6X7Y8Z9A0B1C2D3E4F5G6H7I8J9K0L1M2N3O4P5Q6R7S8T9U0V1W2X3Y4Z5A6B7C8D9E0F1G2H3I4J5K6L7M8N9O0P1Q2R3S4T5U6V7W8X9Y0Z1 admin@desktop',
        ],
        'ecdsa-sha2-nistp256' => [
            'ecdsa-sha2-nistp256 AAAAE2VjZHNhLXNoYTItbmlzdHAyNTYAAAAIbmlzdHAyNTYAAABBBEL/bJlwcqP8E7MfP5ItPmKLQIGLSEfWLCdKLWdD2LGtKt0JGHpJKjWtP7Wp+nnfVvHdYpN8LNpJBVFYhMZQ+c4= user@server',
        ],
    ];

    private const KEY_NAMES = [
        'Personal Laptop Key',
        'Work Desktop Key',
        'CI/CD Deploy Key',
        'Emergency Access Key',
        'Development Key',
        'Production Deploy Key',
        'Backup Access Key',
        'Admin Master Key',
    ];

    public static function class(): string
    {
        return SSHKey::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        $keyType = self::faker()->randomElement(['ssh-ed25519', 'ssh-rsa', 'ecdsa-sha2-nistp256']);
        $publicKey = self::faker()->randomElement(self::SSH_KEYS[$keyType]);

        return [
            'name' => self::faker()->randomElement(self::KEY_NAMES),
            'publicKey' => $publicKey,
            'user' => UserFactory::new(),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public function forUser(User $user): self
    {
        return $this->with(['user' => $user]);
    }

    public function ed25519(): self
    {
        return $this->with([
            'publicKey' => self::faker()->randomElement(self::SSH_KEYS['ssh-ed25519']),
        ]);
    }

    public function rsa(): self
    {
        return $this->with([
            'publicKey' => self::faker()->randomElement(self::SSH_KEYS['ssh-rsa']),
        ]);
    }

    public function ecdsa(): self
    {
        return $this->with([
            'publicKey' => self::faker()->randomElement(self::SSH_KEYS['ecdsa-sha2-nistp256']),
        ]);
    }
}
