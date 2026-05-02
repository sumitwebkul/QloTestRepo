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

## Mock Db::getInstance()

Use PHPUnit's `createMock()` to replace the Db singleton. Always configure `escape()` to pass through — `pSQL()` calls `Db::escape()` and SQL assertions will fail if it returns null.

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

Reset in tearDown:

```php
$ref = new ReflectionProperty(Context::class, 'instance');
$ref->setAccessible(true);
$ref->setValue(null, null);
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

## Tautological Assertions

Never write `assertTrue(true)` — it proves nothing. For tests that only verify no exception is thrown, use:

```php
// Wrong
$obj->someMethod();
$this->assertTrue(true);

// Correct
$this->expectNotToPerformAssertions();
$obj->someMethod();
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
