# QloApps PHPUnit Test File Structure

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

use PHPUnit\Framework\TestCase;

class {ClassName}Test extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Set Configuration values needed by the class under test
        Configuration::set('PS_LANG_DEFAULT', 1);

        // Inject Db mock — escape() must pass through for pSQL() to work in SQL assertions
        $this->dbMock = $this->createMock(Db::class);
        $this->dbMock->method('escape')->willReturnArgument(0);
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->dbMock);

        Cache::resetAll();
    }

    protected function tearDown(): void
    {
        // Reset Db singleton
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        // Reset Context singleton
        $ref = new ReflectionProperty(Context::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        // Reset configurable stub flags
        ObjectModel::$updateResult = true;
        Group::setFeatureActive(true);

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

- Do **not** define constants (`_DB_PREFIX_`, `_PS_ROOT_DIR_`, etc.) — already defined in `tests/bootstrap.php`
- Do **not** declare stub classes inline — add them to `tests/Unit/stubs/CoreStubs.php`
- Do **not** `require_once` class files — the QloApps autoloader handles this

## Naming Conventions

| What | Convention | Example |
|------|-----------|---------|
| Test class | `{ClassName}Test` | `ValidateTest` |
| Test method | `test{MethodName}{Scenario}` | `testIsEmailReturnsTrueForValid` |
| Data provider | `provide{MethodName}` | `provideEmailValidation` |

## Data Provider Pattern

Use `@dataProvider` for methods with multiple input cases:

```php
/**
 * @dataProvider provideEmailValidation
 */
public function testEmailFieldValidation(string $email, bool $expected): void
{
    $this->assertSame($expected, Validate::isEmail($email));
}

public function provideEmailValidation(): array
{
    return [
        'valid email'          => ['user@example.com', true],
        'missing at sign'      => ['userexample.com', false],
        'empty string'         => ['', false],
        'valid with subdomain' => ['user@mail.example.com', true],
    ];
}
```

## Exception Testing Pattern

Use `expectException()` BEFORE the action that throws:

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
```

## No-exception Pattern

For tests that only verify no exception is thrown, use `expectNotToPerformAssertions()` instead of `assertTrue(true)`:

```php
public function testResetCacheOnMissingKeyDoesNotThrow(): void
{
    $this->expectNotToPerformAssertions();
    SomeClass::resetCache(999);
}
```

## Branch Coverage Pattern

Read the method body. Every `if/else` needs two tests:

```php
public function testGetPriceIncludesTaxWhenRateIsPositive(): void
{
    $room = new RoomType();
    $room->price = 100.00;
    $room->tax_rate = 0.10;
    $this->assertSame(110.00, $room->getPrice());
}

public function testGetPriceReturnBaseWhenTaxRateIsZero(): void
{
    $room = new RoomType();
    $room->price = 100.00;
    $room->tax_rate = 0.0;
    $this->assertSame(100.00, $room->getPrice());
}
```

## SQL Assertion Pattern

Use `logicalAnd` when a query must contain multiple predicates:

```php
$this->dbMock
    ->expects($this->once())
    ->method('executeS')
    ->with($this->logicalAnd(
        $this->stringContains('deleted'),
        $this->stringContains('= 0')
    ))
    ->willReturn([]);
```

## What to Test Per Class Type

| Class Type | Test Focus |
|-----------|-----------|
| Static utility (Validate, Tools) | Each public static method, edge cases, invalid inputs, boundary values |
| ObjectModel subclass | `$definition` structure, field validation, constructor defaults, save/delete DB interaction, business logic methods |
| Module class | install/uninstall return values, hook registration, failure when Db returns false |
| Service / helper class | Happy path, all branches, exception propagation, dependency call verification |
| Controller | Covered by integration tests, not unit tests |
