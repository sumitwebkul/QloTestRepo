# QloApps Mock Patterns for Unit Tests

QloApps classes depend on global singletons. Always mock these in setUp() — never let tests hit a real database or read real configuration.

## Bootstrap — What's Already Available

`tests/bootstrap.php` sets up the full test environment before any test runs:

- `_PS_ROOT_DIR_`, `_DB_PREFIX_`, `_PS_USE_SQL_SLAVE_` constants are defined
- All QloApps path constants (`_PS_CLASS_DIR_`, `_PS_CONFIG_DIR_`, etc.) from `config/defines.inc.php`
- `pSQL()` and `bqSQL()` functions from `config/alias.php`
- All core stubs from `tests/Unit/stubs/CoreStubs.php` (Db, ObjectModel, Context, Configuration, Tools, Shop, Cache, and domain stubs)
- QloApps autoloader registered via `config/autoload.php` — real classes load automatically

**Do NOT** define constants or require files in individual test files. Everything is already available.

**Why stubs load before the autoloader:** Every stub uses an `if (!class_exists(...))` guard. Because `CoreStubs.php` is required before `config/autoload.php`, the stub defines the class first. When the autoloader later encounters the real class file, PHP skips it (the class already exists). This is how stubs shadow heavy real classes without modifying them.

**`_PS_IN_TEST_`:** Bootstrap defines `define('_PS_IN_TEST_', true)`. Real QloApps class methods can check this constant to skip side effects (hook calls, file writes, mail sending) during tests. Add this guard to new methods that have side effects you want to suppress in tests:
```php
if (!defined('_PS_IN_TEST_') || !_PS_IN_TEST_) {
    Hook::exec('actionSomeHook');
}
```

## createStub() vs createMock()

PHPUnit 10+ provides two distinct test double creation methods:

| Method | Purpose | Verifies calls? |
|--------|---------|----------------|
| `createStub()` | Return fake data; you don't care if/how it's called | No |
| `createMock()` | Verify the method IS called, how many times, with what args | Yes |

Use `createStub()` for dependencies like `Db` when you only need to control return values. Use `createMock()` when you need to assert calls with `expects()`.

Additional convenience methods:

```php
// createConfiguredStub() — shorthand for stub + willReturn in one call
$db = $this->createConfiguredStub(Db::class, [
    'getValue' => '42',
    'executeS' => [['id_customer' => 1]],
    'escape'   => 'passthrough', // NOTE: use willReturnArgument(0) for escape, not a string
]);

// createConfiguredMock() — same but for mocks that verify calls
$db = $this->createConfiguredMock(Db::class, [
    'insert' => true,
]);

// getMockBuilder() — when you need constructor control
$stub = $this->getMockBuilder(SomeClass::class)
    ->disableOriginalConstructor()  // skip constructor (useful if it needs live DB)
    ->getMock();
```

## Mock Db::getInstance()

Use `createMock()` to replace the Db singleton. Always configure `escape()` to pass through — `pSQL()` calls `Db::escape()` and SQL assertions will fail if it returns null.

```php
class SomeTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbMock = $this->createMock(Db::class);
        // pSQL() calls Db::escape() — pass through so SQL strings contain real values
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

        parent::tearDown();
    }
}
```

Stub query results:

```php
$this->dbMock->method('getRow')->willReturn(['id_address' => 1, 'alias' => 'Home']);
$this->dbMock->method('executeS')->willReturn([['id_tag' => 1, 'name' => 'wifi']]);
$this->dbMock->method('insert')->willReturn(true);
$this->dbMock->method('getValue')->willReturn('42');
```

## Mock Context::getContext()

Only set up Context when the class under test actually calls `Context::getContext()`. The Context stub starts as `null` — methods that never call it need no setup.

```php
protected function setUpContext(): void
{
    $contextMock = $this->createMock(Context::class);
    $contextMock->shop = (object)['id' => 1];
    $contextMock->language = (object)['id' => 1, 'iso_code' => 'en'];

    $ref = new ReflectionProperty(Context::class, 'instance');
    $ref->setAccessible(true);
    $ref->setValue(null, $contextMock);
}
```

**Always** reset Context in `tearDown()` — even if `setUpContext()` is only called in some tests, the reset must be unconditional so a test that sets it cannot bleed into the next one:

```php
protected function tearDown(): void
{
    $ref = new ReflectionProperty(Context::class, 'instance');
    $ref->setAccessible(true);
    $ref->setValue(null, null);
    // ... other resets
}
```

## Configuration stub

The `Configuration` stub in `CoreStubs.php` is an in-memory key-value store. Use `set()` in setUp and `resetAll()` in tearDown:

```php
protected function setUp(): void
{
    Configuration::set('PS_LANG_DEFAULT', 1);
    Configuration::set('PS_CUSTOMER_GROUP', 3);
}

protected function tearDown(): void
{
    Configuration::resetAll();
}
```

## Configurable Stub Flags

`CoreStubs.php` exposes static flags to simulate failure states without creating per-test subclasses.

**ObjectModel::$updateResult** — controls what `update()` returns:

```php
// Make update() fail for this test
ObjectModel::$updateResult = false;
$result = $obj->someMethodThatCallsUpdate();
$this->assertFalse($result);
```

**Group::setFeatureActive()** — controls the `isFeatureActive()` branch:

```php
Group::setFeatureActive(false);
$result = Customer::getGroupsStatic(1); // takes the !isFeatureActive() branch
```

Always reset both in tearDown:

```php
protected function tearDown(): void
{
    ObjectModel::$updateResult = true;
    Group::setFeatureActive(true);
    // ... other resets
}
```

## Verifying DB Calls Were Made

Don't just stub — assert the method actually called Db with the right arguments. For SQL string assertions, use `logicalAnd` when multiple predicates must be present:

```php
// Single predicate
$this->dbMock
    ->expects($this->once())
    ->method('executeS')
    ->with($this->stringContains('WHERE id_hotel ='))
    ->willReturn([]);

// Multiple predicates — use logicalAnd
$this->dbMock
    ->expects($this->once())
    ->method('getValue')
    ->with($this->logicalAnd(
        $this->stringContains('email'),
        $this->stringContains('user@example.com')
    ))
    ->willReturn(false);

// Verify column and value together
$this->dbMock
    ->expects($this->once())
    ->method('executeS')
    ->with($this->logicalAnd(
        $this->stringContains('active'),
        $this->stringContains('= 1')
    ))
    ->willReturn([]);
```

## Password Assertions

Never assert `md5(...)` directly — that couples the test to the stub implementation. Always use `Tools::encrypt()` so the test remains correct if the hashing algorithm changes:

```php
// Wrong — coupled to stub implementation
$this->assertSame(md5('secret'), $customer->passwd);

// Correct — coupled to the class contract
$this->assertSame(Tools::encrypt('secret'), $customer->passwd);
```

The same applies when setting up test fixtures:

```php
// Wrong
$customer->passwd = md5('oldpass');

// Correct
$customer->passwd = Tools::encrypt('oldpass');
```

## Simulating Dependency Failures

```php
$this->dbMock->method('insert')->willReturn(false);
$this->dbMock->method('executeS')->willReturn([]);
$this->dbMock->method('getRow')->willThrowException(new PrestaShopDatabaseException('Connection lost'));
$this->dbMock->method('delete')->willReturn(false);
```

## External Service Responses

For classes that call payment gateways, external APIs, or mail services via an injected dependency, mock the service and assert both success and failure paths:

```php
// Inject mock via constructor or setter
$paymentMock = $this->createMock(PaymentGateway::class);
$paymentMock
    ->expects($this->once())
    ->method('charge')
    ->with($this->equalTo(150.00))
    ->willReturn(['status' => 'success', 'transaction_id' => 'TXN123']);

$service = new BookingPaymentService($paymentMock);
$result = $service->processPayment(150.00);

$this->assertSame('TXN123', $result['transaction_id']);

// Failure case — service returns error
$paymentMock->method('charge')
    ->willReturn(['status' => 'failed', 'error' => 'Insufficient funds']);
$result = $service->processPayment(150.00);
$this->assertFalse($result['success']);
```

For `Mail::Send()` — stub the Mail class in `CoreStubs.php` (already done); verify it is called:

```php
// If Mail is injected or called statically, assert it was triggered
$this->dbMock->method('insert')->willReturn(true);
// Mail::Send is stubbed in CoreStubs — it returns true silently
$result = $customer->transformToCustomer(1, 'validpass');
$this->assertTrue($result); // confirms the Mail::Send path was reached
```

## Consecutive Return Values

When a method is called multiple times in one test and must return different values each time, PHPUnit 10+ offers two equivalent approaches:

```php
// Shorthand — pass multiple args to willReturn() (works in PHPUnit 10+)
$this->dbMock->method('getValue')->willReturn('5', false);

// Equivalent explicit form
$this->dbMock->method('getValue')->willReturnOnConsecutiveCalls('5', false);
```

Both are valid in PHPUnit 10+. Use `willReturn(v1, v2, ...)` for brevity. Essential for testing cache-invalidation flows:

```php
$this->dbMock
    ->expects($this->exactly(2))
    ->method('getValue')
    ->willReturn('5', false);

SomeClass::hasAddress(1, 10);             // first call → '5' (found)
SomeClass::resetCache(1, 10);             // clears static cache
$result = SomeClass::hasAddress(1, 10);   // second call → false (not found)

$this->assertFalse($result);
```

## Asserting Array Arguments with callback()

Use `$this->callback()` when asserting that a method was called with a specific array structure (e.g., verifying the data passed to `insert()`). `stringContains` only works on strings — for arrays, use `callback`:

```php
$this->dbMock
    ->expects($this->once())
    ->method('insert')
    ->with('customer_group', $this->callback(fn($data) => $data['id_group'] === 3))
    ->willReturn(true);

$customer->updateGroup([]);  // should fall back to default group 3
```

Multiple conditions in one callback:

```php
->with('hotel_room', $this->callback(function ($data) {
    return $data['id_hotel'] === 1
        && $data['active'] === 1
        && isset($data['date_add']);
}))
```

## QloApps Internal Cache Key Format

QloApps classes cache DB results using `Cache::store()` / `Cache::isStored()` / `Cache::retrieve()`. The cache key convention is:

```
ClassName::methodName{id}-{secondaryValue}
```

Pre-populate the Cache stub to bypass internal DB calls in methods that check the cache before querying:

```php
// Bypass Customer::checkPassword() DB call in isLogged()
$passwd = Tools::encrypt('secret');
Cache::store('Customer::checkPassword' . $customerId . '-' . $passwd, true);

$customer->logged = 1;
$customer->id = $customerId;
$customer->passwd = $passwd;
$this->assertTrue($customer->isLogged());
```

When testing a method that populates the cache, verify the second call does NOT hit the DB:

```php
$this->dbMock->expects($this->once())->method('executeS')->willReturn([['id_group' => 3]]);
Customer::getGroupsStatic(8);  // hits DB, stores in static cache
Customer::getGroupsStatic(8);  // uses cache — second DB call must NOT happen
```

## Invocation Matchers Reference

| Matcher | Meaning |
|---------|---------|
| `$this->once()` | Called exactly once |
| `$this->never()` | Must never be called |
| `$this->exactly(N)` | Called exactly N times |
| `$this->atLeastOnce()` | Called one or more times |
| `$this->atMost(N)` | Called at most N times |
| `$this->any()` | Called any number of times (use when count doesn't matter) |

```php
// Verify a method is called at least once (e.g. logging)
$this->dbMock->expects($this->atLeastOnce())->method('insert')->willReturn(true);

// Verify a cleanup method is called at most once
$this->dbMock->expects($this->atMost(1))->method('delete')->willReturn(true);
```

## willReturnMap() — Return by Argument Value

Use `willReturnMap()` when a method returns different values depending on which specific argument is passed. Each inner array is `[...args, returnValue]`:

```php
// Configuration::get() returns different values for different keys
$configMap = [
    ['PS_CUSTOMER_GROUP', null, null, null, false, 3],
    ['PS_GUEST_GROUP',    null, null, null, false, 2],
    ['PS_LANG_DEFAULT',  null, null, null, false, 1],
];
$configStub->method('get')->willReturnMap($configMap);
```

```php
// Db::getValue() returns different row for different queries
$this->dbMock->method('getValue')->willReturnMap([
    ['SELECT id_customer FROM ps_customer WHERE email = \'a@b.com\'', false, '5'],
    ['SELECT id_customer FROM ps_customer WHERE email = \'x@y.com\'', false, false],
]);
```

## willReturnCallback() — Computed Return Values

Use `willReturnCallback()` when the return value must be computed from the actual arguments at call time. Useful for testing methods that pass transformed data to dependencies:

```php
// Return the first argument uppercased — verifies the method passes the right string
$this->dbMock->method('escape')
    ->willReturnCallback(fn($str) => strtoupper($str));

// Simulate a DB that returns rows only when the query contains the right table
$this->dbMock->method('executeS')
    ->willReturnCallback(function (string $sql) {
        if (str_contains($sql, 'customer_group')) {
            return [['id_group' => 3]];
        }
        return [];
    });
```

## willReturnSelf() — Fluent Interface Testing

Use `willReturnSelf()` when the mocked method returns `$this` (fluent/builder pattern). This allows you to chain mock calls:

```php
$collection = $this->createMock(PrestaShopCollection::class);
$collection->method('where')->willReturnSelf();
$collection->method('orderBy')->willReturnSelf();
$collection->method('getResults')->willReturn([['id_room' => 1]]);

$result = SomeService::findAvailableRooms($collection);
$this->assertCount(1, $result);
```

## Hook::exec() Return Value Testing

`Hook::exec()` is stubbed in `CoreStubs.php` to return `''` by default. When the class under test checks the **return value** of a hook call, stub it to return the expected value:

```php
// From Address::getZoneById() — checks if a module returned an ID via hook
if (!class_exists('Hook')) {
    // Hook is already stubbed in CoreStubs — but the stub returns ''
    // To simulate a module returning a zone ID, override per-test:
}

// Override the Hook stub for a single test using a subclass or by modifying CoreStubs,
// OR pre-populate Cache if the result is cached, OR test the non-hook branch directly.

// Pattern: test the branch where hook returns numeric value
// Requires Hook stub to support per-test return values.
// Add to CoreStubs.php if needed:
// class Hook { public static $hookReturn = ''; public static function exec($name, $args=[]) { return static::$hookReturn; } }

// Then in test:
Hook::$hookReturn = '5';  // simulate a module returning zone ID 5
$result = Address::getZoneById(1);
$this->assertSame(5, $result);

// Reset in tearDown:
Hook::$hookReturn = '';
```

## Date and Time Testing

Never call `time()` or `date()` directly in test assertions — the result changes every second. Instead:
1. Pass known timestamps as arguments when possible
2. Use fixed date strings for date-range methods
3. For methods that call `time()` internally, test the behavior rather than the exact timestamp

```php
// Test with a fixed known date string
public function testSetTimeModeConvertsDateToTimestamp(): void
{
    $chart = new Chart();
    $chart->setTimeMode('2024-01-01', '2024-01-31', 'd');

    // Assert the from/to properties are set (exact value from strtotime)
    $this->assertSame(strtotime('2024-01-01'), $chart->from);
    $this->assertSame(strtotime('2024-01-31'), $chart->to);
}

// Test date arithmetic boundary — check-in/check-out overlap
public function testRoomIsUnavailableWhenOverlappingBookingExists(): void
{
    $this->dbMock->method('getValue')->willReturn('1'); // overlap found

    $available = HotelBookingData::isRoomAvailable(
        $id_room = 1,
        $date_from = '2024-06-10',
        $date_to = '2024-06-15'
    );
    $this->assertFalse($available);
}

// Test that date_add/date_upd fields get set (not the exact value)
public function testSaveSetsDateAdd(): void
{
    $this->dbMock->method('insert')->willReturn(true);
    $this->dbMock->method('Insert_ID')->willReturn(1);

    $obj = new SomeModel();
    $obj->add();

    $this->assertNotEmpty($obj->date_add);
    $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $obj->date_add);
}
```

## Tautological Assertions

Never write `assertTrue(true)` — it proves nothing. For tests that only verify no exception is thrown, use the `#[DoesNotPerformAssertions]` attribute:

```php
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

// Wrong
$obj->someMethod();
$this->assertTrue(true);

// Correct
#[DoesNotPerformAssertions]
public function testResetCacheOnMissingKeyDoesNotThrow(): void
{
    SomeClass::resetCache(999);
}
```

## Static Methods (Tools, Validate)

PHPUnit cannot mock static methods directly. For `Validate` (pure utility, loaded from the real `classes/Validate.php`) and `Tools` (stub in CoreStubs.php) — call them directly:

```php
$this->assertTrue(Validate::isEmail('user@example.com'));
$this->assertFalse(Validate::isPasswd('ab'));
```

## Permission / Access Control Mocking

```php
protected function setUpEmployeeWithAccess(bool $hasAccess): void
{
    $employeeMock = $this->createMock(Employee::class);
    $employeeMock->method('hasAccess')->willReturn($hasAccess);

    $contextMock = $this->createMock(Context::class);
    $contextMock->employee = $employeeMock;

    $ref = new ReflectionProperty(Context::class, 'instance');
    $ref->setAccessible(true);
    $ref->setValue(null, $contextMock);
}
```
