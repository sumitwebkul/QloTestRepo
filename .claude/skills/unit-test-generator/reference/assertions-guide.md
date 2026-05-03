# PHPUnit 10+ Assertions Guide for QloApps

Pick the most specific assertion for every check. Vague assertions like `assertTrue($x !== null)` hide intent and produce poor failure messages.

---

## Identity vs Equality

| Use | When |
|-----|------|
| `assertSame($expected, $actual)` | Type and value must match (`===`) — default choice for int, string, bool, null |
| `assertEquals($expected, $actual)` | Only value must match (`==`) — use for objects with `equals()` or when type coercion is acceptable |
| `assertNotSame()` / `assertNotEquals()` | Negations of above |

```php
$this->assertSame(42, $customer->id);            // int
$this->assertSame('user@example.com', $customer->email); // string
$this->assertSame(true, $customer->isGuest());   // bool — but assertFalse/assertTrue are clearer
```

---

## Boolean

```php
$this->assertTrue($customer->isLogged());
$this->assertFalse($customer->isGuest());
$this->assertNotTrue($value);   // value is not exactly true (could be null, 0, false)
$this->assertNotFalse($value);  // value is not exactly false
```

---

## Null

```php
$this->assertNull($customer->id);          // not yet persisted
$this->assertNotNull($customer->email);    // must have a value
```

---

## Floats and Prices

**Never use `assertSame()` on floats.** Use `assertEqualsWithDelta()` for all price, tax, commission, and rate calculations:

```php
// Tax rate in QloApps is stored as percentage (12 = 12%), not decimal (0.12)
$this->assertEqualsWithDelta(224.00, $room->getPriceWithTax(), 0.001);
$this->assertEqualsWithDelta(18.00,  $booking->getCommission(), 0.01);

// Comparison
$this->assertGreaterThan(0.0, $room->price);
$this->assertGreaterThanOrEqual(0.0, $discount->getAmount());
$this->assertLessThanOrEqual(100.0, $tax->getRate());
```

---

## Type Checking

```php
$this->assertIsInt($customer->id_default_group);
$this->assertIsString($customer->email);
$this->assertIsBool($customer->is_guest);
$this->assertIsFloat($room->price);
$this->assertIsArray($result);
$this->assertIsObject($context);
$this->assertIsList($rows);               // sequential array with int keys 0..n
$this->assertIsIterable($collection);
$this->assertInstanceOf(Customer::class, $obj);
$this->assertNotInstanceOf(Guest::class, $obj);
```

---

## Arrays

```php
// Key presence
$this->assertArrayHasKey('email', $fields);
$this->assertArrayNotHasKey('password_plain', $exported);

// Value presence
$this->assertContains(3, $groupIds);
$this->assertNotContains(0, $activeRoomIds);

// Count
$this->assertCount(3, $results);
$this->assertNotCount(0, $rooms);
$this->assertEmpty($errors);
$this->assertNotEmpty($bookings);

// Order-independent equality (useful for group ID arrays)
$this->assertEqualsCanonicalizing([3, 5], $customer->getGroups());

// Partial key matching — assert only specific keys, ignore the rest
$keys = ['id_customer', 'email'];
$this->assertSame(
    ['id_customer' => 1, 'email' => 'a@b.com'],
    array_intersect_key($row, array_flip($keys))
);

// Typed content
$this->assertContainsOnly('int', $groupIds);
$this->assertContainsOnly('string', $emailList);
$this->assertContainsOnlyInstancesOf(Customer::class, $customers);
```

---

## Strings

```php
// Containment — preferred over assertContains() for strings
$this->assertStringContainsString('WHERE id_customer', $sql);
$this->assertStringNotContainsString('DROP', $sql);
$this->assertStringContainsStringIgnoringCase('email', $query);

// Prefix / suffix
$this->assertStringStartsWith('ps_', $tableName);
$this->assertStringEndsWith('Test', get_class($this));

// Pattern matching — replaces deprecated assertRegExp()
$this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $booking->date_add);
$this->assertDoesNotMatchRegularExpression('/<script/i', $output);
```

---

## Objects

```php
// Property existence
$this->assertObjectHasProperty('email', $customer);
$this->assertObjectNotHasProperty('credit_card', $customer);

// Custom equals method
$this->assertObjectEquals($expectedMoney, $actualMoney, 'equals');
```

---

## Exceptions

```php
// Always call expect* BEFORE the action that throws
$this->expectException(PrestaShopException::class);
$this->expectExceptionCode(404);
$this->expectExceptionMessage('Room not found');
$this->expectExceptionMessageMatches('/room.*not found/i');  // regex match

SomeClass::getRoom(-1);
```

---

## JSON

```php
$this->assertJson($response);
$this->assertJsonStringEqualsJsonString('{"status":"ok"}', $apiResponse);
$this->assertJsonStringEqualsJsonFile('tests/fixtures/expected.json', $response);
```

---

## QloApps-Specific Patterns

### Checking $definition fields

```php
// Field exists and has required flag
$this->assertArrayHasKey('email', Customer::$definition['fields']);
$this->assertTrue(Customer::$definition['fields']['email']['required']);

// Field size limit
$this->assertSame(128, Customer::$definition['fields']['email']['size']);

// Field type
$this->assertSame(ObjectModel::TYPE_STRING, Customer::$definition['fields']['email']['type']);

// All fields have valid types
$validTypes = [ObjectModel::TYPE_INT, ObjectModel::TYPE_BOOL, ObjectModel::TYPE_STRING,
               ObjectModel::TYPE_FLOAT, ObjectModel::TYPE_DATE, ObjectModel::TYPE_HTML];
foreach (Customer::$definition['fields'] as $field => $spec) {
    $this->assertContains($spec['type'], $validTypes, "Field '$field' has invalid type");
}
```

### Checking SQL query structure

```php
// Single predicate — use stringContains matcher (not assertStringContainsString on the query directly)
$this->dbMock->expects($this->once())->method('executeS')
    ->with($this->stringContains('WHERE id_hotel ='))
    ->willReturn([]);

// Multiple predicates simultaneously
$this->dbMock->expects($this->once())->method('executeS')
    ->with($this->logicalAnd(
        $this->stringContains('active'),
        $this->stringContains('= 1'),
        $this->stringContains('deleted'),
        $this->stringContains('= 0')
    ))
    ->willReturn([]);

// Checking returned query result count
$result = Customer::getCustomers();
$this->assertCount(2, $result);
$this->assertContainsOnly('array', $result);
```

### Checking prices and calculations

```php
// Always use assertEqualsWithDelta for float math
$this->assertEqualsWithDelta(110.00, $room->getPriceWithTax(), 0.001);
$this->assertGreaterThan(0.0, $booking->getTotalCost());

// Integer prices (stored as cents or multiplied values)
$this->assertSame(10000, $room->getPriceInCents());
```

---

## Assertion Choice Guide

| Scenario | Use |
|---------|-----|
| Exact int/string/bool value | `assertSame()` |
| Float / price / rate value | `assertEqualsWithDelta()` |
| Array contains a value | `assertContains()` |
| Array key exists | `assertArrayHasKey()` |
| Array count | `assertCount()` |
| Two arrays same regardless of order | `assertEqualsCanonicalizing()` |
| String contains substring | `assertStringContainsString()` |
| String matches pattern | `assertMatchesRegularExpression()` |
| Object has property | `assertObjectHasProperty()` |
| Correct PHP type | `assertIsInt()`, `assertIsString()`, `assertIsArray()`, etc. |
| Value is null | `assertNull()` |
| Value not null | `assertNotNull()` |
| True / False exactly | `assertTrue()` / `assertFalse()` |
| Exception thrown | `expectException()` + `expectExceptionMessage()` |
| No assertion needed | `#[DoesNotPerformAssertions]` attribute |
