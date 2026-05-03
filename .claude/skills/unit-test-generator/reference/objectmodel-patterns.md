# Testing ObjectModel Subclasses in QloApps

ObjectModel subclasses (Customer, Address, Cart, etc.) have a standard structure. Unit tests for these focus on the definition, validation, and logic methods — not on CRUD (which requires a real DB and belongs to integration tests).

## What to Test in an ObjectModel

| Area | Test approach |
|------|--------------|
| `$definition` structure | Assert required keys exist with correct types |
| Field validation | Test each `validate` rule via `Validate::{method}()` |
| Constructor defaults | Instantiate without ID, check default property values |
| Custom static methods | Mock Db and assert return values |
| Custom instance methods | Mock Db, call method, assert result |

## Testing the $definition Array

```php
public function testDefinitionHasRequiredStructure(): void
{
    $this->assertArrayHasKey('table', Tag::$definition);
    $this->assertArrayHasKey('primary', Tag::$definition);
    $this->assertArrayHasKey('fields', Tag::$definition);
    $this->assertIsArray(Tag::$definition['fields']);
}

public function testDefinitionFieldsHaveValidTypes(): void
{
    foreach (Tag::$definition['fields'] as $field => $spec) {
        $this->assertArrayHasKey('type', $spec, "Field '$field' missing type");
        $this->assertContains(
            $spec['type'],
            [ObjectModel::TYPE_INT, ObjectModel::TYPE_STRING, ObjectModel::TYPE_BOOL,
             ObjectModel::TYPE_FLOAT, ObjectModel::TYPE_DATE, ObjectModel::TYPE_HTML,
             ObjectModel::TYPE_NOTHING],
            "Field '$field' has invalid type"
        );
    }
}
```

## Testing Constructor Defaults

Test both the no-ID and with-ID forms. The no-ID form checks defaults; the with-ID form verifies the ID is retained (the stub `ObjectModel::__construct` sets `$this->id = $id`):

```php
public function testConstructorWithNoIdSetsNullId(): void
{
    $tag = new Tag();
    $this->assertNull($tag->id);
}

public function testConstructorWithIdRetainsId(): void
{
    $tag = new Tag(42);
    $this->assertSame(42, $tag->id);
}
```

## Testing a Custom Static Method (with Db mock)

```php
public function testGetProductTagsReturnsTagNames(): void
{
    $this->dbMock
        ->method('executeS')
        ->willReturn([['name' => 'wifi'], ['name' => 'pool']]);

    $result = Tag::getProductTags(1, 1);

    $this->assertIsArray($result);
    $this->assertContains('wifi', array_column($result, 'name'));
}

public function testGetProductTagsReturnsEmptyArrayWhenNoneFound(): void
{
    $this->dbMock->method('executeS')->willReturn([]);
    $result = Tag::getProductTags(99, 1);
    $this->assertSame([], $result);
}
```

## Testing Field Validation Rules

Use the real `Validate` class (loaded from `classes/Validate.php` via autoloader — no stub needed):

```php
use PHPUnit\Framework\Attributes\DataProvider;

#[DataProvider('provideNameValidation')]
public function testTagNameValidation(string $name, bool $isValid): void
{
    $this->assertSame($isValid, Validate::isGenericName($name));
}

public static function provideNameValidation(): array
{
    return [
        'valid name'       => ['wifi', true],
        'valid with space' => ['free wifi', true],
        'empty string'     => ['', false],
        'with HTML'        => ['<b>tag</b>', false],
    ];
}
```

## Testing Business Logic (QloApps Domain)

Use `assertEqualsWithDelta()` for all float/price results — never `assertSame()`. In QloApps, tax rates are stored as percentages (12 = 12%), not decimals (0.12):

```php
public function testGetRoomPriceWithTaxApplied(): void
{
    $room = new HtlRoomType();
    $room->price = 200.00;
    $room->tax_rate = 12; // 12% stored as integer percentage
    $this->assertEqualsWithDelta(224.00, $room->getPriceWithTax(), 0.001);
}

public function testGetRoomPriceWithZeroTax(): void
{
    $room = new HtlRoomType();
    $room->price = 200.00;
    $room->tax_rate = 0;
    $this->assertEqualsWithDelta(200.00, $room->getPriceWithTax(), 0.001);
}
```

## Testing update() Failure Branch

`ObjectModel::$updateResult` in `CoreStubs.php` controls what the stub `update()` returns. Use it to test branches that depend on update success/failure:

```php
public function testSomeMethodReturnsFalseWhenUpdateFails(): void
{
    $this->dbMock->method('delete')->willReturn(true);
    $this->dbMock->method('insert')->willReturn(true);
    ObjectModel::$updateResult = false;

    $obj = new SomeModel();
    $obj->active = 1;

    $this->assertFalse($obj->someMethodThatCallsUpdate());
}
```

Always reset in tearDown: `ObjectModel::$updateResult = true;`

## Testing State Mutations

After calling a method that changes object state, assert the changed properties directly.

The stub `ObjectModel::add()` calls `Db::getInstance()->Insert_ID()` and sets `$this->id`. Stub `Insert_ID()` to control what ID is assigned:

```php
public function testAddSetsIdAfterInsert(): void
{
    $this->dbMock->method('insert')->willReturn(true);
    $this->dbMock->method('Insert_ID')->willReturn(42);

    $tag = new Tag();
    $tag->name = 'wifi';
    $tag->add();

    $this->assertSame(42, $tag->id);
}
```

```php
public function testTransformToCustomerSetsIsGuestToZero(): void
{
    $this->dbMock->method('delete')->willReturn(true);
    $this->dbMock->method('insert')->willReturn(true);

    $customer = new Customer();
    $customer->is_guest = 1;
    $customer->id = 5;

    $customer->transformToCustomer(1, 'validpass');

    $this->assertSame(0, $customer->is_guest);
}
```

## Complete Example: TagTest

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

class TagTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbMock = $this->createMock(Db::class);
        $this->dbMock->method('escape')->willReturnArgument(0);
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->dbMock);
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, null);

        ObjectModel::$updateResult = true;
        Group::setFeatureActive(true);
        Configuration::resetAll();
        Cache::resetAll();

        parent::tearDown();
    }

    public function testDefinitionTableIsTag(): void
    {
        $this->assertSame('tag', Tag::$definition['table']);
    }

    public function testDefinitionPrimaryIsIdTag(): void
    {
        $this->assertSame('id_tag', Tag::$definition['primary']);
    }

    public function testConstructorDefaultsToNullId(): void
    {
        $tag = new Tag();
        $this->assertNull($tag->id);
    }

    #[DataProvider('provideNameValidation')]
    public function testNameFieldValidation(string $name, bool $expected): void
    {
        $this->assertSame($expected, Validate::isGenericName($name));
    }

    public static function provideNameValidation(): array
    {
        return [
            'valid'     => ['wifi', true],
            'empty'     => ['', false],
            'with HTML' => ['<b>bad</b>', false],
        ];
    }
}
```
