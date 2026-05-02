# QloApps PHPUnit Test File Structure

## File Placement

- Test files go in: `tests/Unit/{ClassName}Test.php`
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
    protected function setUp(): void
    {
        parent::setUp();
        // initialize mocks and test subject here
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // reset static state if any
    }

    public function testMethodNameExpectedBehavior(): void
    {
        // Arrange
        // Act
        // Assert
    }
}
```

## Naming Conventions

| What | Convention | Example |
|------|-----------|---------|
| Test class | `{ClassName}Test` | `ValidateTest` |
| Test method | `test{MethodName}{Scenario}` | `testIsEmailReturnsTrueForValid` |
| Data provider | `provide{MethodName}Cases` | `provideIsEmailCases` |

## Data Provider Pattern

Use `@dataProvider` for methods with multiple input cases (especially validation methods):

```php
/**
 * @dataProvider provideIsEmailCases
 */
public function testIsEmail(string $input, bool $expected): void
{
    $this->assertSame($expected, Validate::isEmail($input));
}

public function provideIsEmailCases(): array
{
    return [
        'valid email'           => ['user@example.com', true],
        'missing at sign'       => ['userexample.com', false],
        'empty string'          => ['', false],
        'valid with subdomain'  => ['user@mail.example.com', true],
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

## Branch Coverage Pattern

Read the method body. Every `if/else` needs two tests:

```php
// Method: public function getPrice() { if ($this->tax_rate > 0) { return $base * (1 + $this->tax_rate); } return $base; }

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

## Interaction Verification Pattern

Assert the dependency was actually called (not just that a value was returned):

```php
public function testSaveCallsDbInsertOnce(): void
{
    $this->dbMock
        ->expects($this->once())
        ->method('insert')
        ->with($this->equalTo('ps_room_type'), $this->anything())
        ->willReturn(true);

    $obj = new RoomType();
    $obj->name = 'Deluxe';
    $result = $obj->add();

    $this->assertTrue($result);
}
```

## Failure Scenario Pattern

Stub the dependency to fail and assert the method responds correctly:

```php
public function testSaveReturnsFalseWhenDbInsertFails(): void
{
    $this->dbMock
        ->method('insert')
        ->willReturn(false);

    $obj = new RoomType();
    $result = $obj->add();

    $this->assertFalse($result);
}
```

## What to Test Per Class Type

| Class Type | Test Focus |
|-----------|-----------|
| Static utility (Validate, Tools) | Each public static method, edge cases, invalid inputs, boundary values |
| ObjectModel subclass | `$definition` structure, field validation, constructor defaults, save/delete DB interaction, business logic methods |
| Module class | install/uninstall return values, hook registration, failure when Db returns false |
| Service / helper class | Happy path, all branches, exception propagation, dependency call verification |
| Controller | Covered by integration tests, not unit tests |
