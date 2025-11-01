<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentProxyObjectFactory;

/**
 * @extends PersistentProxyObjectFactory<User>
 */
final class UserFactory extends PersistentProxyObjectFactory
{
    private static UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        parent::__construct();
        self::$passwordHasher = $passwordHasher;
    }

    public static function class(): string
    {
        return User::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->email(),
            'name' => self::faker()->name(),
            'roles' => ['ROLE_USER'],
            'password' => 'password123', // Will be hashed in afterInstantiate
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this
            ->afterInstantiate(function (User $user): void {
                if (!str_starts_with($user->getPassword(), '$')) {
                    // Hash the password if it's not already hashed
                    $hashedPassword = self::$passwordHasher->hashPassword($user, $user->getPassword());
                    $user->setPassword($hashedPassword);
                }
            });
    }

    public function asAdmin(): self
    {
        return $this->with([
            'roles' => ['ROLE_ADMIN'],
        ]);
    }

    public function withPlainPassword(string $password): self
    {
        return $this->with([
            'password' => $password,
        ]);
    }
}
