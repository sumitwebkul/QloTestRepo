# Advanced PHPUnit Patterns for QloApps

This guide covers patterns needed for non-trivial class structures: protected/private method testing, partial mocks, abstract classes, fluent interfaces, multilang/multishop ObjectModels, date-dependent logic, and more.

---

## 1. Testing Protected / Private Methods

Use `ReflectionMethod` to call non-public methods directly. This is preferable to making them public just for testing.

```php
public function testProtectedCalculateTaxReturnsCorrectAmount(): void
{
    $method = new ReflectionMethod(HtlRoomType::class, 'calculateTax');
    $method->setAccessible(true);

    $room = new HtlRoomType();
    $result = $method->invoke($room, 200.00, 12); // price, tax_rate

    $this->assertEqualsWithDelta(24.00, $result, 0.001);
}
```

### Reading / writing protected properties

```php
public function testProtectedPropertyDefaultsToZero(): void
{
    $obj = new SomeModel();
    $prop = new ReflectionProperty(SomeModel::class, '_internalCount');
    $prop->setAccessible(true);

    $this->assertSame(0, $prop->getValue($obj));
}

public function testProtectedPropertyCanBePrimed(): void
{
    $obj = new SomeModel();
    $prop = new ReflectionProperty(SomeModel::class, '_internalCount');
    $prop->setAccessible(true);
    $prop->setValue($obj, 5);

    $this->assertSame(5, $prop->getValue($obj));
}
```

### Resetting static caches via ReflectionProperty

Static properties survive between tests. Always reset them in BOTH `setUp()` and `tearDown()`:

```php
protected function setUp(): void
{
    parent::setUp();
    $this->resetStaticCache();
}

protected function tearDown(): void
{
    $this->resetStaticCache();
    parent::tearDown();
}

private function resetStaticCache(): void
{
    $prop = new ReflectionProperty(HtlRoomType::class, '_cache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
}
```

---

## 2. Partial Mocks with onlyMethods()

When only some methods need stubbing but you want the real implementation for others, use `getMockBuilder()->onlyMethods()`. This is the correct API since PHPUnit 9 (replaces the removed `setMethods()`):

```php
public function testSaveCallsDbInsertAndSetsId(): void
{
    $this->dbMock->method('insert')->willReturn(true);
    $this->dbMock->method('Insert_ID')->willReturn(99);

    $room = $this->getMockBuilder(HtlRoomType::class)
        ->onlyMethods(['validateFields'])   // stub validation only
        ->getMock();
    $room->method('validateFields')->willReturn(true);
    $room->name = 'Suite';

    $room->add();

    $this->assertSame(99, $room->id);
}
```

**Rules for partial mocks:**
- Only override methods that have side effects or are expensive — leave business logic real
- If `__construct` does heavy work, use `->disableOriginalConstructor()` and set properties manually
- `onlyMethods()` only accepts methods that actually exist — it will throw if the method name is wrong

```php
$obj = $this->getMockBuilder(SomeClass::class)
    ->disableOriginalConstructor()
    ->onlyMethods(['sendEmail', 'logError'])
    ->getMock();
$obj->id = 5;
$obj->name = 'Test';
```

---

## 3. Testing Abstract Classes

Use an anonymous subclass to exercise concrete methods on an abstract class:

```php
public function testAbstractModelDefinitionHasTable(): void
{
    $concrete = new class extends AbstractHtlModel {
        public static $definition = [
            'table'   => 'htl_abstract',
            'primary' => 'id_htl_abstract',
            'fields'  => [],
        ];
    };

    $this->assertSame('htl_abstract', $concrete::$definition['table']);
}

public function testAbstractGetFormattedPriceReturnsString(): void
{
    $concrete = new class(null, 100.00) extends AbstractHtlModel {
        public function __construct($id, public float $price) {}
    };

    $result = $concrete->getFormattedPrice(); // concrete method on abstract parent

    $this->assertIsString($result);
    $this->assertStringContainsString('100', $result);
}
```

---

## 4. Fluent Interface Testing (return $this)

Methods that return `$this` (builder/fluent patterns) are tested with `willReturnSelf()` when mocking collaborators:

```php
// Testing a QueryBuilder-style object
public function testQueryBuilderChainsWhereAndLimit(): void
{
    $builder = new HtlQueryBuilder();

    $result = $builder
        ->forHotel(1)
        ->availableFrom('2024-01-01')
        ->availableTo('2024-01-07')
        ->limit(10);

    $this->assertSame($builder, $result); // chain returns same object
    $this->assertSame(1, $builder->getHotelId());
    $this->assertSame(10, $builder->getLimit());
}

// When testing a class that USES a fluent collaborator, mock it with willReturnSelf():
public function testBookingServiceUsesQueryBuilder(): void
{
    $builderMock = $this->createMock(HtlQueryBuilder::class);
    $builderMock->method('forHotel')->willReturnSelf();
    $builderMock->method('availableFrom')->willReturnSelf();
    $builderMock->method('availableTo')->willReturnSelf();
    $builderMock->method('limit')->willReturnSelf();
    $builderMock->method('execute')->willReturn([['id_room' => 5]]);

    $service = new BookingService($builderMock);
    $rooms = $service->findAvailable(1, '2024-01-01', '2024-01-07');

    $this->assertCount(1, $rooms);
}
```

---

## 5. Date and Time Dependent Logic

Never let tests call `date('now')` or `time()` freely — results vary by run. Use fixed known timestamps or date strings.

### Pattern A: Pass date as parameter (preferred)

```php
public function testIsBookingExpiredReturnsTrueForPastDate(): void
{
    $booking = new HotelBookingDetail();
    $booking->date_checkout = '2020-01-01'; // clearly in the past

    $this->assertTrue($booking->isExpired());
}

public function testIsBookingExpiredReturnsFalseForFutureDate(): void
{
    $booking = new HotelBookingDetail();
    $booking->date_checkout = '2099-12-31'; // clearly in the future

    $this->assertFalse($booking->isExpired());
}
```

### Pattern B: Use strtotime() with known arithmetic

```php
public function testGetNightsCountForKnownDateRange(): void
{
    $booking = new HotelBookingDetail();
    $booking->date_checkin  = '2024-06-01';
    $booking->date_checkout = '2024-06-04';

    $this->assertSame(3, $booking->getNightsCount());
}
```

### Pattern C: Assert date format with regex

When a method generates a date (e.g., `date_add` on save), assert the format rather than the exact value:

```php
public function testDateAddFieldMatchesDateFormat(): void
{
    $booking = new HotelBookingDetail();
    $booking->date_add = date('Y-m-d H:i:s');

    $this->assertMatchesRegularExpression(
        '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
        $booking->date_add
    );
}
```

### Pattern D: Override time-returning methods via partial mock

```php
public function testGetCurrentDateReturnsExpected(): void
{
    $obj = $this->getMockBuilder(BookingCalendar::class)
        ->onlyMethods(['getCurrentTimestamp'])
        ->getMock();
    $obj->method('getCurrentTimestamp')->willReturn(mktime(0, 0, 0, 6, 15, 2024));

    $this->assertSame('2024-06-15', $obj->getCurrentDate());
}
```

---

## 6. Multilang ObjectModel Patterns

Multilang ObjectModels store translatable fields in a `_lang` table. The `$definition` has `multilang = true` and field specs include `'lang' => true`.

### Testing definition structure

```php
public function testDefinitionIsMultilang(): void
{
    $this->assertTrue(HtlRoomType::$definition['multilang']);
}

public function testNameFieldIsMultilang(): void
{
    $this->assertTrue(HtlRoomType::$definition['fields']['name']['lang']);
}
```

### Testing multilang data retrieval

When a method calls `executeS()` with a JOIN on the `_lang` table, assert both tables appear in the query:

```php
public function testGetRoomTypesByLanguageQueriesLangTable(): void
{
    $this->dbMock
        ->expects($this->once())
        ->method('executeS')
        ->with($this->logicalAnd(
            $this->stringContains('htl_room_type'),
            $this->stringContains('htl_room_type_lang'),
            $this->stringContains('id_lang')
        ))
        ->willReturn([['id_htl_room_type' => 1, 'name' => 'Suite']]);

    $result = HtlRoomType::getRoomTypesByLanguage(1);

    $this->assertCount(1, $result);
    $this->assertSame('Suite', $result[0]['name']);
}
```

### Testing multilang fallback

```php
public function testGetRoomTypeNameFallsBackToDefaultLanguage(): void
{
    $this->dbMock
        ->method('executeS')
        ->willReturnOnConsecutiveCalls(
            [],                                        // id_lang=5 — not found
            [['name' => 'Default Room']]               // id_lang=1 — fallback
        );

    $result = HtlRoomType::getName(1, 5); // (id_room_type, id_lang)

    $this->assertSame('Default Room', $result);
}
```

---

## 7. Multishop ObjectModel Patterns

Multishop ObjectModels join on `_shop` association tables. The `$definition` has `multishop = true`.

### Testing definition

```php
public function testDefinitionSupportsMultishop(): void
{
    $this->assertTrue(HtlBranchInfo::$definition['multishop']);
}
```

### Testing shop-scoped queries

```php
public function testGetHotelsForShopIncludesShopFilter(): void
{
    $this->dbMock
        ->expects($this->once())
        ->method('executeS')
        ->with($this->logicalAnd(
            $this->stringContains('id_shop'),
            $this->stringContains('htl_branch_info_shop')
        ))
        ->willReturn([['id_htl_branch_info' => 1]]);

    $result = HtlBranchInfo::getHotelsByShop(1);
    $this->assertCount(1, $result);
}
```

---

## 8. Classes Using Hook::exec() Return Values

When a class dispatches a hook and branches on the return value, override the `Hook` stub in that test only:

```php
public function testGetPriceUsesHookReturnWhenNonEmpty(): void
{
    // Override Hook::exec for this test via ReflectionProperty or subclassing.
    // Easiest approach: create a test-local anonymous class that overrides exec,
    // but since Hook is already loaded, use getMockBuilder instead:

    // If the class under test calls Hook::exec() statically, wrap it:
    // The Hook stub in CoreStubs.php always returns ''. To test hook return branches,
    // prime Configuration or use a partial mock that overrides the method that
    // calls Hook::exec():

    $room = $this->getMockBuilder(HtlRoomType::class)
        ->onlyMethods(['getHookPrice'])
        ->getMock();
    $room->method('getHookPrice')->willReturn(150.00); // simulates hook returning a value

    $room->price = 200.00;
    // If hook overrides price, assert 150; otherwise assert 200
    $this->assertEqualsWithDelta(150.00, $room->getEffectivePrice(), 0.001);
}

public function testGetPriceIgnoresEmptyHookReturn(): void
{
    $room = $this->getMockBuilder(HtlRoomType::class)
        ->onlyMethods(['getHookPrice'])
        ->getMock();
    $room->method('getHookPrice')->willReturn(null); // hook returned nothing

    $room->price = 200.00;
    $this->assertEqualsWithDelta(200.00, $room->getEffectivePrice(), 0.001);
}
```

---

## 9. Consecutive Return Values

When a method is called multiple times in a single test and must return different values each time:

```php
// Pass multiple values to willReturn() — works in PHPUnit 10+
$this->dbMock->method('executeS')
    ->willReturn(
        [['id' => 1]],   // first call
        [['id' => 2]],   // second call
        []               // third call — empty
    );
```

---

## 10. Callback-Based Return Values

When the return value must be computed from the actual argument passed:

```php
$this->dbMock->method('getValue')
    ->willReturnCallback(function (string $sql) {
        if (str_contains($sql, 'id_customer = 1')) {
            return 'user@example.com';
        }
        return false;
    });
```

---

## 11. willReturnMap() for Argument-Based Routing

When a method is called with different args and must return specific values for each combination:

```php
$this->dbMock->method('getValue')
    ->willReturnMap([
        ['SELECT email FROM ... WHERE id = 1', 'user@example.com'],
        ['SELECT email FROM ... WHERE id = 2', 'other@example.com'],
        ['SELECT email FROM ... WHERE id = 9', false],
    ]);
```

---

## 12. Invocation Count Assertions

| Method | Meaning |
|--------|---------|
| `$this->once()` | Called exactly 1 time |
| `$this->never()` | Never called |
| `$this->exactly(N)` | Called exactly N times |
| `$this->atLeastOnce()` | Called ≥ 1 time |
| `$this->atMost(N)` | Called ≤ N times |
| `$this->any()` | No count constraint |

```php
$this->dbMock->expects($this->never())
    ->method('insert');

$this->dbMock->expects($this->exactly(2))
    ->method('executeS')
    ->willReturn([]);
```

---

## 13. Argument Capture with $this->callback()

Assert the exact structure of an argument passed to a mock:

```php
$this->dbMock
    ->expects($this->once())
    ->method('insert')
    ->with($this->callback(function (string $table, array $data) {
        return $table === 'htl_room_type'
            && isset($data['name'])
            && $data['active'] === 1;
    }))
    ->willReturn(true);
```

---

## 14. Exception Testing

```php
public function testGetRoomThrowsForInvalidId(): void
{
    $this->expectException(PrestaShopException::class);
    $this->expectExceptionMessage('Room not found');

    HtlRoomType::getRoom(-1);
}

public function testConstructorThrowsWhenDbFails(): void
{
    $this->dbMock->method('getRow')->willReturn(false);

    $this->expectException(PrestaShopDatabaseException::class);

    new HtlRoomType(999);
}
```

---

## 15. setUpBeforeClass() for Shared Expensive Fixtures

Use `setUpBeforeClass()` when a fixture is expensive to create and safe to share (no mutation between tests):

```php
private static array $roomTypes;

public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();
    // Load static fixture data once for the entire test class
    static::$roomTypes = [
        ['id_htl_room_type' => 1, 'name' => 'Suite', 'price' => 200.00],
        ['id_htl_room_type' => 2, 'name' => 'Deluxe', 'price' => 150.00],
    ];
}
```

**Do NOT** use `setUpBeforeClass()` for Db mocks or static state — those must be isolated per test via `setUp()`/`tearDown()`.

---

## 16. Data Providers for Range-Tested Methods

Use `#[DataProvider]` with `public static` for any method that behaves differently across input ranges. Use `#[TestWith]` for simple inline cases:

```php
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;

// Inline single-row — use TestWith
#[TestWith([0, false])]
#[TestWith([1, true])]
#[TestWith([-1, false])]
public function testIsPositiveId(int $id, bool $expected): void
{
    $this->assertSame($expected, HtlRoomType::isValidId($id));
}

// Multiple rows from data set — use DataProvider
#[DataProvider('provideNightsCounts')]
public function testNightsCount(string $checkin, string $checkout, int $expected): void
{
    $booking = new HotelBookingDetail();
    $booking->date_checkin  = $checkin;
    $booking->date_checkout = $checkout;

    $this->assertSame($expected, $booking->getNightsCount());
}

public static function provideNightsCounts(): array
{
    return [
        'one night'    => ['2024-06-01', '2024-06-02', 1],
        'three nights' => ['2024-06-01', '2024-06-04', 3],
        'same day'     => ['2024-06-01', '2024-06-01', 0],
        'month span'   => ['2024-06-28', '2024-07-03', 5],
    ];
}
```

---

## 17. Tests That Must Not Assert Anything

When a method's only value is that it doesn't throw, use `#[DoesNotPerformAssertions]`:

```php
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

#[DoesNotPerformAssertions]
public function testLogErrorDoesNotThrow(): void
{
    $logger = new HtlLogger();
    $logger->logError('Test error message'); // assert: no exception thrown
}
```

---

## 18. Skipping and Incomplete Tests

```php
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

#[RequiresPhpExtension('gd')]
public function testGenerateRoomImageThumbnail(): void
{
    // Only runs when GD extension is available
}

public function testComplexPricingAlgorithm(): void
{
    $this->markTestIncomplete('Waiting for pricing spec from product team');
}

public function testLegacyPaymentFlow(): void
{
    $this->markTestSkipped('Legacy flow removed in v2.0');
}
```

---

## Quick Decision Guide

| Situation | Pattern |
|-----------|---------|
| Method is `protected`/`private` | `ReflectionMethod::setAccessible(true)` |
| Only a few methods need stubbing | `getMockBuilder()->onlyMethods([...])` |
| Constructor is heavy | `->disableOriginalConstructor()` + set properties manually |
| Class is `abstract` | Anonymous subclass via `new class extends AbstractX {}` |
| Method returns `$this` | `willReturnSelf()` on collaborator mock |
| Method depends on `date()`/`time()` | Use fixed known date strings or partial mock |
| `$definition` has `multilang = true` | Assert `_lang` table appears in SQL |
| `$definition` has `multishop = true` | Assert `_shop` table appears in SQL |
| Method calls `Hook::exec()` | Partial mock the method calling it, or stub the method that returns the hook value |
| Multiple sequential calls return different values | `willReturn(v1, v2, v3, ...)` |
| Return value depends on argument | `willReturnCallback()` or `willReturnMap()` |
| Method should never be called | `expects($this->never())` |
| Method is called N times exactly | `expects($this->exactly(N))` |
| Method must not throw | `#[DoesNotPerformAssertions]` |
