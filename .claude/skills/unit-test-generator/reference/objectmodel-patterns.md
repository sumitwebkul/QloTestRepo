# Testing ObjectModel Subclasses in QloApps

ObjectModel subclasses (Address, Tag, Cart, etc.) have a standard structure. Unit tests for these focus on the definition, validation, and logic methods — not on CRUD (which requires a real DB and belongs to integration tests).

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

```php
public function testConstructorSetsDefaultValues(): void
{
    // Requires Db mock (parent::__construct calls Db if $id is set)
    $tag = new Tag();  // no ID = no DB call

    $this->assertNull($tag->id);
    $this->assertNull($tag->name);
}
```

## Testing a Custom Static Method (with Db mock)

Example: `Tag::getProductTags($id_product, $id_lang)`

```php
public function testGetProductTagsReturnsTagNames(): void
{
    $this->dbMock
        ->method('executeS')
        ->willReturn([
            ['name' => 'wifi'],
            ['name' => 'pool'],
        ]);

    $result = Tag::getProductTags(1, 1);

    $this->assertIsArray($result);
    $this->assertContains('wifi', array_column($result, 'name'));
}

public function testGetProductTagsReturnsEmptyArrayWhenNoneFound(): void
{
    $this->dbMock
        ->method('executeS')
        ->willReturn([]);

    $result = Tag::getProductTags(99, 1);

    $this->assertSame([], $result);
}
```

## Testing Field Validation Rules

Cross-check each field's `validate` rule:

```php
/**
 * @dataProvider provideNameValidation
 */
public function testTagNameValidation(string $name, bool $isValid): void
{
    $this->assertSame($isValid, Validate::isGenericName($name));
}

public function provideNameValidation(): array
{
    return [
        'valid name'        => ['wifi', true],
        'valid with space'  => ['free wifi', true],
        'empty string'      => ['', false],
        'too long (33)'     => [str_repeat('a', 33), false],
        'with HTML'         => ['<b>tag</b>', false],
    ];
}
```

## Testing Business Logic (QloApps Domain)

For hotel/room pricing, availability, tax, commission, and date-range methods — use known inputs and hand-computed expected outputs:

```php
public function testGetRoomPriceWithTaxApplied(): void
{
    $room = new HtlRoomType();
    $room->price = 200.00;
    $room->tax_rate = 0.12;  // 12%

    $result = $room->getPriceWithTax();

    $this->assertSame(224.00, $result);  // 200 * 1.12 = 224
}

public function testGetAvailableRoomsExcludesBookedDates(): void
{
    $this->dbMock
        ->method('executeS')
        ->willReturn([['id_room' => 3], ['id_room' => 7]]);

    $ids = HtlRoomType::getAvailableRoomIds(1, '2024-06-01', '2024-06-05');

    $this->assertNotContains(3, $ids);  // room 3 is booked
}

public function testCommissionCalculationForPartner(): void
{
    $booking = new HtlBookingDetail();
    $booking->total_price = 500.00;
    $booking->commission_rate = 0.15;  // 15%

    $this->assertSame(75.00, $booking->getCommissionAmount());
    $this->assertSame(425.00, $booking->getNetAmount());
}
```

## Testing State Mutations

After calling a method that changes object state, assert the changed properties:

```php
public function testAddSetsIdAfterInsert(): void
{
    $this->dbMock->method('insert')->willReturn(true);
    $this->dbMock->method('Insert_ID')->willReturn(42);

    $tag = new Tag();
    $tag->name = 'wifi';
    $tag->add();

    $this->assertSame(42, (int) $tag->id);
}

public function testDeleteSetsIdToNull(): void
{
    $this->dbMock->method('delete')->willReturn(true);

    $tag = new Tag(1);
    $tag->delete();

    $this->assertNull($tag->id);
}
```

## Complete Example: TagTest

```php
<?php

use PHPUnit\Framework\TestCase;

class TagTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        if (!defined('_DB_PREFIX_')) {
            define('_DB_PREFIX_', 'ps_');
        }

        $this->dbMock = $this->createMock(Db::class);

        $reflection = new ReflectionProperty(Db::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, $this->dbMock);
    }

    protected function tearDown(): void
    {
        $reflection = new ReflectionProperty(Db::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
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

    /**
     * @dataProvider provideNameValidation
     */
    public function testNameFieldValidation(string $name, bool $expected): void
    {
        $this->assertSame($expected, Validate::isGenericName($name));
    }

    public function provideNameValidation(): array
    {
        return [
            'valid'     => ['wifi', true],
            'empty'     => ['', false],
            'too long'  => [str_repeat('x', 33), false],
        ];
    }
}
```
