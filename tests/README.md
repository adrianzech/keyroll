# KeyRoll Testing Guide

This project includes comprehensive PHPUnit tests covering entities, repositories, factories, and controllers.

## Test Structure

```
tests/
├── Unit/
│   └── Entity/          # Entity unit tests (no database required)
├── Integration/
│   ├── Factory/         # Foundry factory tests (database required)
│   └── Repository/      # Repository tests (database required)
└── Functional/
    └── Controller/      # Controller/HTTP tests (database required)
```

## Running Tests

### Run All Tests

```bash
php bin/phpunit
```

### Run Specific Test Suites

```bash
# Unit tests only (no database needed)
php bin/phpunit tests/Unit

# Integration tests
php bin/phpunit tests/Integration

# Functional tests
php bin/phpunit tests/Functional
```

### Run with Test Coverage

```bash
# With coverage report
XDEBUG_MODE=coverage php bin/phpunit --coverage-html var/coverage

# Then open var/coverage/index.html in a browser
```

### Run in Testdox Format (Pretty Output)

```bash
php bin/phpunit --testdox
```

## Test Database Configuration

Tests use SQLite by default for simplicity (no database server required). The configuration is in `.env.test`:

```env
DATABASE_URL="sqlite:///%kernel.project_dir%/var/test.db"
```

### Using MySQL for Tests (Optional)

If you prefer to use MySQL:

1. Create a separate test database:
```bash
mysql -u root -p -e "CREATE DATABASE keyroll_test;"
```

2. Update `.env.test`:
```env
DATABASE_URL="mysql://user:password@127.0.0.1:3306/keyroll_test?serverVersion=11.4.5-MariaDB&charset=utf8mb4"
```

## Test Categories

### Unit Tests (tests/Unit/)

Test individual classes in isolation without database:

- **UserTest**: User entity methods and relationships
- **HostTest**: Host entity, timestamps, and connection status
- **CategoryTest**: Category entity and relationships
- **SSHKeyTest**: SSH key entity and validation

**Example:**
```bash
php bin/phpunit tests/Unit/Entity/UserTest.php
```

### Integration Tests (tests/Integration/)

Test database interactions and factories:

- **UserRepositoryTest**: Database persistence and queries
- **UserFactoryTest**: Foundry factory functionality
- **HostFactoryTest**: Host creation with modifiers

**Example:**
```bash
php bin/phpunit tests/Integration/Factory/UserFactoryTest.php
```

### Functional Tests (tests/Functional/)

Test full application behavior via HTTP:

- **SecurityControllerTest**: Login, registration, authentication
- **HostControllerTest**: CRUD operations, access control

**Example:**
```bash
php bin/phpunit tests/Functional/Controller/SecurityControllerTest.php
```

## Writing New Tests

### Unit Test Template

```php
<?php

namespace App\Tests\Unit\Entity;

use App\Entity\YourEntity;
use PHPUnit\Framework\TestCase;

class YourEntityTest extends TestCase
{
    public function testSomething(): void
    {
        $entity = new YourEntity();
        // ... assertions
        $this->assertEquals('expected', $entity->getSomething());
    }
}
```

### Integration Test with Factory

```php
<?php

namespace App\Tests\Integration\Factory;

use App\Factory\YourFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class YourFactoryTest extends KernelTestCase
{
    use ResetDatabase;  // Resets database before each test
    use Factories;       // Enables Foundry factories

    public function testCreateEntity(): void
    {
        $entity = YourFactory::createOne();

        $this->assertNotNull($entity->getId());
    }
}
```

### Functional Test

```php
<?php

namespace App\Tests\Functional\Controller;

use App\Factory\UserFactory;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

class YourControllerTest extends WebTestCase
{
    use ResetDatabase;
    use Factories;

    public function testPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/your-route');

        $this->assertResponseIsSuccessful();
    }

    public function testAuthenticatedAction(): void
    {
        $user = UserFactory::createOne();

        $client = static::createClient();
        $client->loginUser($user->object());
        $client->request('GET', '/protected-route');

        $this->assertResponseIsSuccessful();
    }
}
```

## Test Data with Foundry

Tests use Zenstruck Foundry for creating test data:

```php
// Create a single user
$user = UserFactory::createOne();

// Create an admin
$admin = UserFactory::new()->asAdmin()->create();

// Create multiple entities
$hosts = HostFactory::createMany(10);

// Create with specific data
$host = HostFactory::createOne([
    'name' => 'Test Server',
    'hostname' => 'test.example.com',
]);

// Use helper methods
$webServer = HostFactory::new()
    ->asWebServer(1)
    ->connected()
    ->create();
```

## Continuous Integration

For CI/CD pipelines, use these commands:

```bash
# Setup test database
php bin/console doctrine:database:create --env=test
php bin/console doctrine:schema:create --env=test

# Run tests
php bin/phpunit

# With coverage (requires Xdebug or pcov)
XDEBUG_MODE=coverage php bin/phpunit --coverage-clover coverage.xml
```

## Troubleshooting

### Database Connection Errors

If you get "Connection refused" errors:

1. Check that your test database is configured in `.env.test`
2. For SQLite: ensure `var/` directory is writable
3. For MySQL: ensure the database exists and credentials are correct

### Foundry ResetDatabase Issues

The `ResetDatabase` trait automatically drops and recreates the database schema for each test class. If you encounter issues:

1. Ensure the test database exists
2. Check file permissions for SQLite
3. Verify doctrine is properly configured

### Memory Limits

For tests creating many entities:

```bash
php -d memory_limit=512M bin/phpunit
```

## Best Practices

1. **Keep unit tests fast**: Don't use database for unit tests
2. **Use factories**: Create test data with Foundry factories
3. **Reset database**: Use `ResetDatabase` trait for isolation
4. **Test behavior, not implementation**: Focus on what, not how
5. **Descriptive names**: Test method names should describe what they test
6. **One assertion per test**: Keep tests focused and simple

## Test Fixtures

Use `AppFixtures` for development, but create test data with factories in tests:

```php
// DON'T do this in tests
php bin/console doctrine:fixtures:load --env=test

// DO this instead
$users = UserFactory::createMany(3);
$hosts = HostFactory::createMany(5);
```

This ensures test isolation and makes tests faster.
