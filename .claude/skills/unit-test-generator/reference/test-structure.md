# QloApps PHPUnit Test File Structure

> **PHPUnit version:** This project uses PHPUnit 10+. All `@annotation` forms are **deprecated in PHPUnit 11 and will be removed in PHPUnit 12**. Always use `#[Attribute]` forms.

## File Placement

- Test files go in: `tests/Unit/{ClassName}Test.php`
- Stubs for new dependencies go in: `tests/Unit/stubs/CoreStubs.php`
- Class name inside: `{ClassName}Test`
- Extends: `PHPUnit\Framework\TestCase`

## Standard Template

```php
<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License version 3.0
 * that is bundled with this package in the file LICENSE.md
 *
 * @author    Webkul IN
 * @copyright Since 2010 Webkul
 * @license   https://opensource.org/license/osl-3-0-php Open Software License version 3.0
 */

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class {ClassName}Test extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Set ALL Configuration values read by the class constructor and methods
        Configuration::set('PS_LANG_DEFAULT', 1);

        // Inject Db mock — escape() must pass through for pSQL() to work in SQL assertions
        $this->dbMock = $this->createMock(Db::class);
        $this->dbMock->method('escape')->willReturnArgument(0);
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->dbMock);

        // If the class has static cache properties, reset them here AND in tearDown()
        // so stale state from a failed previous test cannot bleed in.
        // $ref = new ReflectionProperty({ClassName}Core::class, '_cache');
        // $ref->setAccessible(true);
        // $ref->setValue(null, []);

        Cache::resetAll();
    }

    protected function tearDown(): void
    {
        // Reset Db singleton
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        // Reset Context singleton — always unconditional even if setUpContext() was only
        // called in some tests, so no test bleeds into the next.
        $ref = new ReflectionProperty(Context::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        // Reset configurable stub flags only if used by this class
        ObjectModel::$updateResult = true;
        Group::setFeatureActive(true);

        // If the class constructor mutates $definition at runtime, undo it here. Example:
        // unset({ClassName}::$definition['fields']['phone']['required']);

        // Mirror the setUp() static cache reset
        // $ref = new ReflectionProperty({ClassName}Core::class, '_cache');
        // $ref->setAccessible(true);
        // $ref->setValue(null, []);

        Configuration::resetAll();
        Cache::resetAll();

        parent::tearDown();
    }

    public function testMethodNameExpectedBehavior(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## What NOT to put in test files

- Do **not** define constants (`_DB_PREFIX_`, `_PS_ROOT_DIR_`, etc.) — already in `tests/bootstrap.php`
- Do **not** declare stub classes inline — add them to `tests/Unit/stubs/CoreStubs.php`
- Do **not** `require_once` class files — the QloApps autoloader handles this
- Do **not** use `@annotation` forms — they are deprecated; use `#[Attribute]` forms

## Naming Conventions

| What | Convention | Example |
|------|-----------|---------|
| Test class | `{ClassName}Test` | `ValidateTest` |
| Test method | `test{MethodName}{Scenario}` | `testIsEmailReturnsTrueForValid` |
| Data provider | `provide{MethodName}` | `provideEmailValidation` |

---

## Data Provider Pattern

Data provider methods **MUST be `public static`** (required since PHPUnit 10). Use the `#[DataProvider]` attribute — the `@dataProvider` annotation is deprecated.

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('provideEmailValidation')]
public function testEmailFieldValidation(string $email, bool $expected): void
{
    $this->assertSame($expected, Validate::isEmail($email));
}

public static function provideEmailValidation(): array
{
    return [
        'valid email'          => ['user@example.com', true],
        'missing at sign'      => ['userexample.com', false],
        'empty string'         => ['', false],
        'valid with subdomain' => ['user@mail.example.com', true],
    ];
}
```

For small inline datasets, use `#[TestWith]` to avoid a separate provider method:

```php
use PHPUnit\Framework\Attributes\TestWith;

#[TestWith(['user@example.com', true])]
#[TestWith(['notanemail', false])]
#[TestWith(['', false])]
public function testIsEmail(string $email, bool $expected): void
{
    $this->assertSame($expected, Validate::isEmail($email));
}
```

---

## Exception Testing Pattern

Call `expectException()` **before** the action that throws. Use `expectExceptionMessage()` for exact match or `expectExceptionMessageMatches()` for regex:

```php
public function testSaveThrowsOnDuplicateKey(): void
{
    $this->dbMock
        ->method('insert')
        ->willThrowException(new PrestaShopDatabaseException('Duplicate entry'));

    $this->expectException(PrestaShopDatabaseException::class);
    $this->expectExceptionMessage('Duplicate entry');

    $obj = new SomeModel();
    $obj->save();
}

public function testThrowsWithCodeOnAuthFailure(): void
{
    $this->expectException(PrestaShopException::class);
    $this->expectExceptionCode(403);
    $this->expectExceptionMessageMatches('/not authorized/i');

    SomeClass::requiresAuth();
}
```

---

## No-assertion Tests

For tests that only verify no exception is thrown, use the `#[DoesNotPerformAssertions]` attribute (replaces `expectNotToPerformAssertions()`):

```php
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

#[DoesNotPerformAssertions]
public function testResetCacheOnMissingKeyDoesNotThrow(): void
{
    SomeClass::resetCache(999);
}
```

---

## Float / Price Assertion Pattern

Never use `assertSame()` for floats — use `assertEqualsWithDelta()` to handle floating-point imprecision. Essential for QloApps price, tax, and commission calculations:

```php
public function testGetPriceWithTaxApplied(): void
{
    $room = new HtlRoomType();
    $room->price = 200.00;
    // tax_rate stored as percentage in QloApps (12 = 12%), not decimal (0.12)
    $room->tax_rate = 12;
    $this->assertEqualsWithDelta(224.00, $room->getPriceWithTax(), 0.001);
}
```

---

## Branch Coverage Pattern

Read the method body. Every `if/else` branch needs its own test:

```php
public function testGetPriceIncludesTaxWhenRateIsPositive(): void
{
    $room = new RoomType();
    $room->price = 100.00;
    $room->tax_rate = 10; // 10%
    $this->assertEqualsWithDelta(110.00, $room->getPrice(), 0.001);
}

public function testGetPriceReturnsBaseWhenTaxRateIsZero(): void
{
    $room = new RoomType();
    $room->price = 100.00;
    $room->tax_rate = 0;
    $this->assertEqualsWithDelta(100.00, $room->getPrice(), 0.001);
}
```

---

## SQL Assertion Pattern

Use `logicalAnd` when a query must satisfy multiple conditions simultaneously. Use `assertStringContainsString()` for plain string checks:

```php
// Multiple SQL predicates at once
$this->dbMock
    ->expects($this->once())
    ->method('executeS')
    ->with($this->logicalAnd(
        $this->stringContains('deleted'),
        $this->stringContains('= 0')
    ))
    ->willReturn([]);

// Checking a returned SQL string value
$this->assertStringContainsString('ORDER BY', $result['query']);
```

---

## Incomplete and Skipped Tests

Mark work-in-progress tests with `markTestIncomplete()` rather than leaving them empty:

```php
public function testComplexPricingLogic(): void
{
    $this->markTestIncomplete('Pricing formula needs review from domain team.');
}
```

Skip tests when preconditions are missing. Use `#[RequiresPhpExtension]` for extension checks:

```php
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('soap')]
public function testSoapApiIntegration(): void
{
    // skipped automatically if ext-soap not loaded
}

public function testRequiresExternalService(): void
{
    if (!getenv('PAYMENT_API_KEY')) {
        $this->markTestSkipped('Payment API key not configured.');
    }
    // ...
}
```

---

## Shared Expensive Fixtures

Use `setUpBeforeClass()` / `tearDownAfterClass()` (static methods) for resources that are expensive to create and safe to share across all tests in the class. Do NOT use assertions inside them.

```php
private static SomeExpensiveResource $resource;

public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();
    static::$resource = new SomeExpensiveResource();
}

public static function tearDownAfterClass(): void
{
    static::$resource->close();
    parent::tearDownAfterClass();
}
```

---

## Automatic Static Property Backup

As an alternative to manual `ReflectionProperty` resets in `setUp()`/`tearDown()`, use `#[BackupStaticProperties]` on the test class or a single test method. PHPUnit will automatically save and restore all static properties between tests:

```php
use PHPUnit\Framework\Attributes\BackupStaticProperties;
use PHPUnit\Framework\Attributes\ExcludeStaticPropertyFromBackup;

// Back up all static properties of the listed classes
#[BackupStaticProperties(enabled: true)]
class SomeModelTest extends TestCase { ... }

// Or exclude specific properties from backup
#[ExcludeStaticPropertyFromBackup(CustomerCore::class, '_customer_groups')]
public function testSomething(): void { ... }
```

Use `#[BackupStaticProperties]` for simple cases. Use manual `ReflectionProperty` resets when you need fine-grained control (e.g., only resetting specific caches or resetting in both `setUp()` and `tearDown()`).

---

## What to Test Per Class Type

| Class Type | Test Focus |
|-----------|-----------|
| Static utility (Validate, Tools) | Each public static method, edge cases, invalid inputs, boundary values — use `#[DataProvider]` |
| ObjectModel subclass | `$definition` structure, field validation, constructor with/without ID, DB interaction, business logic, state mutations |
| Module class | `install()`/`uninstall()` return values, hook registration, DB failure handling |
| Service / helper class | Happy path, all branches, exception propagation, dependency call verification |
| Controller | Covered by integration tests (`tests/e2e/`), not unit tests |
