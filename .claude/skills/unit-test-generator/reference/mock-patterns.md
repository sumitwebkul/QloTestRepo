# QloApps Mock Patterns for Unit Tests

QloApps classes depend on global singletons. Always mock these in setUp() — never let tests hit a real database or read real configuration.

## Bootstrap Requirement

The test bootstrap (`tests/bootstrap.php`) only loads the composer autoloader. QloApps core is NOT bootstrapped. This means:
- No database connection available
- No `_PS_ROOT_DIR_` constant defined
- No Context, Configuration, or Db instances

You must define required constants and mock required classes before instantiating the class under test.

## Define Required Constants

Add to setUp() or at the top of the test class:

```php
protected function setUp(): void
{
    if (!defined('_DB_PREFIX_')) {
        define('_DB_PREFIX_', 'ps_');
    }
    if (!defined('_PS_ROOT_DIR_')) {
        define('_PS_ROOT_DIR_', dirname(__DIR__, 2));
    }
}
```

## Mock Db::getInstance()

Use PHPUnit's `createMock()` to replace the Db singleton:

```php
use PHPUnit\Framework\TestCase;

class SomeTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(Db::class);

        // Inject mock into Db singleton
        $reflection = new ReflectionProperty(Db::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, $this->dbMock);
    }

    protected function tearDown(): void
    {
        // Reset Db singleton after each test
        $reflection = new ReflectionProperty(Db::class, 'instance');
        $reflection->setAccessible(true);
        $reflection->setValue(null, null);
    }
}
```

Stub query results:

```php
$this->dbMock
    ->method('getRow')
    ->willReturn(['id_address' => 1, 'alias' => 'Home']);

$this->dbMock
    ->method('executeS')
    ->willReturn([
        ['id_tag' => 1, 'name' => 'wifi'],
        ['id_tag' => 2, 'name' => 'pool'],
    ]);

$this->dbMock
    ->method('insert')
    ->willReturn(true);

$this->dbMock
    ->method('getValue')
    ->willReturn('42');
```

## Mock Context::getContext()

```php
protected function setUpContext(): void
{
    $languageMock = $this->createMock(Language::class);
    $languageMock->id = 1;
    $languageMock->iso_code = 'en';

    $shopMock = $this->createMock(Shop::class);
    $shopMock->id = 1;

    $contextMock = $this->createMock(Context::class);
    $contextMock->language = $languageMock;
    $contextMock->shop = $shopMock;

    // Replace Context singleton
    $reflection = new ReflectionProperty(Context::class, 'instance');
    $reflection->setAccessible(true);
    $reflection->setValue(null, $contextMock);
}
```

## Mock Configuration::get()

Configuration uses a static cache. Use a map stub:

```php
// Simple approach: use a value map
$this->getMockBuilder(Configuration::class)
    ->disableOriginalConstructor()
    ->getMock();

// Or stub individual calls using a callback
// Note: Configuration::get() is static, use runkit or override class in test
// Simplest: define constants that Configuration falls back to, or
// use Reflection to inject into Configuration::$_cache

protected function setConfigValue(string $key, $value): void
{
    $cache = &Configuration::$_cache;  // if accessible
    $cache[$key][0] = $value;          // shop_id 0 = global
}
```

## Mock Static Methods (Tools, Validate)

PHPUnit cannot mock static methods directly. Options:

**Option 1 — Test the static method directly (preferred for pure functions):**
```php
// Validate and Tools are pure utility classes — call them directly
$this->assertTrue(Validate::isEmail('user@example.com'));
```

**Option 2 — Override in test namespace (advanced):**
Create a subclass that overrides the static method for testing:
```php
class TestableTools extends Tools
{
    public static function getValue($key, $defaultValue = false)
    {
        return static::$testValues[$key] ?? $defaultValue;
    }
    public static array $testValues = [];
}
```

## Verifying DB Calls Were Made

Don't just stub — assert the method actually called Db with the right arguments:

```php
// Assert insert is called exactly once with the right table
$this->dbMock
    ->expects($this->once())
    ->method('insert')
    ->with(
        $this->equalTo('ps_htl_room_type'),
        $this->arrayHasKey('name')
    )
    ->willReturn(true);

// Assert executeS is called with a SQL string matching a pattern
$this->dbMock
    ->expects($this->once())
    ->method('executeS')
    ->with($this->stringContains('WHERE id_hotel ='))
    ->willReturn([['id_room' => 1]]);
```

## Simulating Dependency Failures

Always write one test where the dependency returns a failure value:

```php
// DB insert fails
$this->dbMock->method('insert')->willReturn(false);

// DB query returns empty
$this->dbMock->method('executeS')->willReturn([]);

// DB throws exception
$this->dbMock->method('getRow')
    ->willThrowException(new PrestaShopDatabaseException('Connection lost'));

// DB delete fails
$this->dbMock->method('delete')->willReturn(false);
```

## Mocking Payment / External HTTP Services

For classes that call payment gateways or external APIs via a service object:

```php
// Inject a mock via constructor or setter
$paymentMock = $this->createMock(PaymentGateway::class);
$paymentMock
    ->expects($this->once())
    ->method('charge')
    ->with($this->equalTo(150.00))
    ->willReturn(['status' => 'success', 'transaction_id' => 'TXN123']);

$service = new BookingPaymentService($paymentMock);
$result = $service->processPayment(150.00);

$this->assertSame('TXN123', $result['transaction_id']);

// Failure case
$paymentMock->method('charge')->willReturn(['status' => 'failed', 'error' => 'Insufficient funds']);
$result = $service->processPayment(150.00);
$this->assertFalse($result['success']);
```

## Permission / Access Control Mocking

For methods that check employee permissions:

```php
protected function setUpEmployeeWithAccess(bool $hasAccess): void
{
    $employeeMock = $this->createMock(Employee::class);
    $employeeMock->method('hasAccess')->willReturn($hasAccess);
    $employeeMock->id = 1;
    $employeeMock->id_profile = $hasAccess ? 1 : 2;

    $contextMock = $this->createMock(Context::class);
    $contextMock->employee = $employeeMock;

    $reflection = new ReflectionProperty(Context::class, 'instance');
    $reflection->setAccessible(true);
    $reflection->setValue(null, $contextMock);
}

// Usage
public function testActionAllowedWhenEmployeeHasPermission(): void
{
    $this->setUpEmployeeWithAccess(true);
    $result = $this->subject->performRestrictedAction();
    $this->assertTrue($result);
}

public function testActionDeniedWhenEmployeeLacksPermission(): void
{
    $this->setUpEmployeeWithAccess(false);
    $result = $this->subject->performRestrictedAction();
    $this->assertFalse($result);
}
```

## When NOT to Mock

- `Validate` — pure static methods, test directly without mocking
- `Tools` string utilities (`strtolower`, `substr`, etc.) — test directly
- Simple value objects with no external dependencies — instantiate directly
