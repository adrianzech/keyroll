<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\Category;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Category>
 */
final class CategoryFactory extends PersistentObjectFactory
{
    private const CATEGORY_NAMES = [
        'Production Servers',
        'Staging Servers',
        'Development Servers',
        'Web Servers',
        'Database Servers',
        'API Servers',
        'Cache Servers',
        'Mail Servers',
        'Monitoring',
        'CI/CD Pipeline',
        'Customer A Infrastructure',
        'Customer B Infrastructure',
        'Internal Services',
        'External Services',
        'Legacy Systems',
    ];

    public static function class(): string
    {
        return Category::class;
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#model-factories
     */
    protected function defaults(): array
    {
        return [
            'name' => self::faker()->unique()->randomElement(self::CATEGORY_NAMES),
        ];
    }

    /**
     * @see https://symfony.com/bundles/ZenstruckFoundryBundle/current/index.html#initialization
     */
    protected function initialize(): static
    {
        return $this;
    }

    public function production(): self
    {
        return $this->with(['name' => 'Production Servers']);
    }

    public function staging(): self
    {
        return $this->with(['name' => 'Staging Servers']);
    }

    public function development(): self
    {
        return $this->with(['name' => 'Development Servers']);
    }
}
