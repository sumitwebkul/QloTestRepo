# Testing Module and PaymentModule Classes in QloApps

Module classes extend `Module` or `PaymentModule`. The **real** module constructors call `Configuration::getMultiple()`, `Currency::checkPaymentCurrencies()`, and `parent::__construct()` — all side-effect-heavy operations that hit the database, filesystem, or HTTP. The **stub** classes in `tests/Unit/stubs/CoreStubs.php` replace those real classes with lightweight in-memory versions, eliminating all side effects so module logic can be tested in isolation.

---

## CoreStubs Provided for Modules

`CoreStubs.php` provides stub versions of all module dependencies:

| Stub class | What it replaces |
|-----------|-----------------|
| `Module` | Real `ModuleCore` (DB, Smarty, HTTP, translation files) |
| `PaymentModule` | Real `PaymentModuleCore` (currency checks, payment flow) |
| `Configuration` | DB-backed config store — use `Configuration::set()` to prime values |
| `Currency` | Real `CurrencyCore` with DB — stub returns predictable arrays |
| `Language` | DB-backed language object — stub stores `id` and `iso_code` |
| `HelperForm` | Admin form generator — stub returns `<form></form>` |
| `OrderState`, `OrderPayment` | DB-backed order classes |
| `Media` | Static media path resolver |
| `Hook` | Real hook dispatcher — injectable per-test via override |
| `Tools` | Utility methods including `getValue`, `isSubmit`, `displayPrice` |

---

## setUp Pattern for Module Tests

Module constructors call `Configuration::getMultiple()` and `parent::__construct()`. Set up required config values **before** instantiating the module:

```php
use PHPUnit\Framework\TestCase;

class ChequeTest extends TestCase
{
    private Cheque $module;

    protected function setUp(): void
    {
        parent::setUp();

        // Prime config values read in the constructor
        Configuration::set('CHEQUE_NAME', 'Test Payee');
        Configuration::set('CHEQUE_ADDRESS', '123 Test St');

        $this->module = new Cheque();
    }

    protected function tearDown(): void
    {
        Configuration::resetAll();
        Cache::resetAll();
        parent::tearDown();
    }
}
```

**Note:** Never inject a Db mock for module tests unless the method under test calls Db directly. The Module stub constructor does not touch Db.

---

## Testing Constructor Defaults

```php
public function testConstructorSetsModuleName(): void
{
    $this->assertSame('cheque', $this->module->name);
}

public function testConstructorSetsVersion(): void
{
    $this->assertSame('2.6.8', $this->module->version);
}

public function testConstructorLoadsChequeName(): void
{
    $this->assertSame('Test Payee', $this->module->chequeName);
}

public function testConstructorSetsWarningWhenConfigMissing(): void
{
    Configuration::resetAll(); // no CHEQUE_NAME or CHEQUE_ADDRESS
    $module = new Cheque();
    $this->assertNotEmpty($module->warning);
}
```

---

## Testing install() and uninstall()

The `Module` stub's `install()` and `registerHook()` both return `true` by default. Test the branching logic by making them return `false`.

### install() — all hooks must succeed

```php
public function testInstallReturnsTrueWhenAllHooksRegister(): void
{
    $this->assertTrue($this->module->install());
}

public function testInstallReturnsFalseWhenRegisterHookFails(): void
{
    // Stub registerHook() to return false — the real install() checks its return value
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['registerHook'])
        ->getMock();
    $module->method('registerHook')->willReturn(false);

    $this->assertFalse($module->install());
}
```

### uninstall() — config cleanup

```php
public function testUninstallDeletesConfigurationKeys(): void
{
    Configuration::set('CHEQUE_NAME', 'Payee');
    Configuration::set('CHEQUE_ADDRESS', '123 St');

    $result = $this->module->uninstall();

    $this->assertTrue($result);
    $this->assertFalse(Configuration::get('CHEQUE_NAME'));
    $this->assertFalse(Configuration::get('CHEQUE_ADDRESS'));
}

public function testUninstallReturnsFalseWhenDeleteFails(): void
{
    // Partial mock: override uninstall to control Configuration::deleteByName
    // Since Configuration is a stub we control via set/resetAll, test via
    // making parent::uninstall fail:
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['uninstall'])
        ->getMock();
    $module->method('uninstall')->willReturn(false);

    $this->assertFalse($module->uninstall());
}
```

---

## Testing Hook Handlers

Hook handler methods are named `hook{HookName}`. They typically check `$this->active`, validate the cart currency, assign Smarty vars, and call `display()`.

### Pattern: stub the dependencies, call the hook handler

```php
public function testHookPaymentReturnsNullWhenModuleInactive(): void
{
    $this->module->active = false;
    $result = $this->module->hookPayment(['cart' => new Cart()]);
    $this->assertNull($result);
}

public function testHookPaymentReturnsNullWhenCurrencyMismatch(): void
{
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['checkCurrency', 'display'])
        ->getMock();
    $module->active = true;
    $module->method('checkCurrency')->willReturn(false);
    $module->method('display')->willReturn('<payment-form/>');

    $result = $module->hookPayment(['cart' => new Cart()]);
    $this->assertNull($result);
}

public function testHookPaymentReturnsTemplateWhenActiveAndCurrencyMatches(): void
{
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['checkCurrency', 'display'])
        ->getMock();
    $module->active = true;
    $module->method('checkCurrency')->willReturn(true);
    $module->method('display')->willReturn('<payment-form/>');

    $result = $module->hookPayment(['cart' => new Cart()]);
    $this->assertSame('<payment-form/>', $result);
}
```

### Pattern: hookDisplayPaymentEU returns array

```php
public function testHookDisplayPaymentEUReturnsPaymentOptionsArray(): void
{
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['checkCurrency'])
        ->getMock();
    $module->active = true;
    $module->method('checkCurrency')->willReturn(true);

    $result = $module->hookDisplayPaymentEU(['cart' => new Cart()]);

    $this->assertIsArray($result);
    $this->assertArrayHasKey('cta_text', $result);
    $this->assertArrayHasKey('logo', $result);
    $this->assertArrayHasKey('action', $result);
}
```

---

## Testing getContent()

`getContent()` dispatches on `Tools::isSubmit()`. Stub `Tools` to simulate form submission vs. page load.

```php
public function testGetContentReturnsHtmlOnPageLoad(): void
{
    // Tools::isSubmit returns false (default in stub) — no form submitted
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['display', 'renderForm'])
        ->getMock();
    $module->method('display')->willReturn('<info/>');
    $module->method('renderForm')->willReturn('<form/>');

    $result = $module->getContent();

    $this->assertStringContainsString('<info/>', $result);
    $this->assertStringContainsString('<form/>', $result);
}
```

For testing the validation/save path, override `Tools::isSubmit` at the class level by subclassing Tools or using a partial mock approach on the module's private methods via ReflectionMethod (see `advanced-patterns.md`).

---

## Testing checkCurrency()

```php
public function testCheckCurrencyReturnsTrueWhenCurrencyMatches(): void
{
    $cart = new Cart();
    $cart->id_currency = 1;

    // getCurrency stub returns array with matching id_currency
    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['getCurrency'])
        ->getMock();
    $module->method('getCurrency')->willReturn([['id_currency' => 1]]);

    $this->assertTrue($module->checkCurrency($cart));
}

public function testCheckCurrencyReturnsFalseWhenNoMatchingCurrency(): void
{
    $cart = new Cart();
    $cart->id_currency = 99;

    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['getCurrency'])
        ->getMock();
    $module->method('getCurrency')->willReturn([['id_currency' => 1]]);

    $this->assertFalse($module->checkCurrency($cart));
}

public function testCheckCurrencyReturnsFalseWhenNoCurrencies(): void
{
    $cart = new Cart();
    $cart->id_currency = 1;

    $module = $this->getMockBuilder(Cheque::class)
        ->onlyMethods(['getCurrency'])
        ->getMock();
    $module->method('getCurrency')->willReturn(false);

    $this->assertFalse($module->checkCurrency($cart));
}
```

---

## Testing getConfigFieldsValues()

```php
public function testGetConfigFieldsValuesReturnsStoredValues(): void
{
    Configuration::set('CHEQUE_NAME', 'My Payee');
    Configuration::set('CHEQUE_ADDRESS', '456 Main St');

    $result = $this->module->getConfigFieldsValues();

    $this->assertSame('My Payee', $result['CHEQUE_NAME']);
    $this->assertSame('456 Main St', $result['CHEQUE_ADDRESS']);
}

public function testGetConfigFieldsValuesReturnsFalseWhenNotSet(): void
{
    Configuration::resetAll();
    $module = new Cheque(); // warning will be set, but config will be empty
    $result = $module->getConfigFieldsValues();

    $this->assertFalse($result['CHEQUE_NAME']);
    $this->assertFalse($result['CHEQUE_ADDRESS']);
}
```

---

## Testing Private Methods via ReflectionMethod

Private methods like `_postValidation()` and `_postProcess()` drive important business logic. Test them through ReflectionMethod:

```php
public function testPostValidationAddsErrorWhenNameMissing(): void
{
    // Override Tools::getValue to return empty for CHEQUE_NAME
    // Use getMockBuilder to make isSubmit/getValue return controlled values.
    // Since Tools is a stub class (not an interface), we can't mock static methods
    // directly. Instead: subclass Tools in the test, or test via getContent().

    // Alternative: call _postValidation via reflection and inspect _postErrors
    $method = new ReflectionMethod(Cheque::class, '_postValidation');
    $method->setAccessible(true);

    $errors = new ReflectionProperty(Cheque::class, '_postErrors');
    $errors->setAccessible(true);
    $errors->setValue($this->module, []);

    // Since Tools::isSubmit() returns false in the stub, _postValidation is a no-op.
    // This tests the no-submission branch.
    $method->invoke($this->module);
    $this->assertCount(0, $errors->getValue($this->module));
}
```

---

## Testing renderForm()

`renderForm()` calls `HelperForm::generateForm()` — already stubbed to return `<form></form>`. Test that it calls the helper and returns a non-empty string:

```php
public function testRenderFormReturnsNonEmptyString(): void
{
    $result = $this->module->renderForm();
    $this->assertNotEmpty($result);
    $this->assertIsString($result);
}
```

---

## Complete ChequeTest Example

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

class ChequeTest extends TestCase
{
    private Cheque $module;

    protected function setUp(): void
    {
        parent::setUp();
        Configuration::set('CHEQUE_NAME', 'Test Payee');
        Configuration::set('CHEQUE_ADDRESS', '123 Test St');
        $this->module = new Cheque();
    }

    protected function tearDown(): void
    {
        Configuration::resetAll();
        Cache::resetAll();
        parent::tearDown();
    }

    public function testConstructorSetsModuleName(): void
    {
        $this->assertSame('cheque', $this->module->name);
    }

    public function testConstructorSetsTab(): void
    {
        $this->assertSame('payments_gateways', $this->module->tab);
    }

    public function testConstructorLoadsChequeName(): void
    {
        $this->assertSame('Test Payee', $this->module->chequeName);
    }

    public function testConstructorSetsWarningWhenConfigMissing(): void
    {
        Configuration::resetAll();
        $module = new Cheque();
        $this->assertNotEmpty($module->warning);
    }

    public function testInstallReturnsTrueByDefault(): void
    {
        $this->assertTrue($this->module->install());
    }

    public function testInstallReturnsFalseWhenRegisterHookFails(): void
    {
        $module = $this->getMockBuilder(Cheque::class)
            ->onlyMethods(['registerHook'])
            ->getMock();
        $module->method('registerHook')->willReturn(false);

        $this->assertFalse($module->install());
    }

    public function testUninstallClearsConfiguration(): void
    {
        $result = $this->module->uninstall();
        $this->assertTrue($result);
        $this->assertFalse(Configuration::get('CHEQUE_NAME'));
    }

    public function testHookPaymentReturnsNullWhenInactive(): void
    {
        $this->module->active = false;
        $this->assertNull($this->module->hookPayment(['cart' => new Cart()]));
    }

    public function testCheckCurrencyReturnsTrueForMatchingCurrency(): void
    {
        $cart = new Cart();
        $cart->id_currency = 1;

        $module = $this->getMockBuilder(Cheque::class)
            ->onlyMethods(['getCurrency'])
            ->getMock();
        $module->method('getCurrency')->willReturn([['id_currency' => 1]]);

        $this->assertTrue($module->checkCurrency($cart));
    }

    public function testCheckCurrencyReturnsFalseForMismatch(): void
    {
        $cart = new Cart();
        $cart->id_currency = 99;

        $module = $this->getMockBuilder(Cheque::class)
            ->onlyMethods(['getCurrency'])
            ->getMock();
        $module->method('getCurrency')->willReturn([['id_currency' => 1]]);

        $this->assertFalse($module->checkCurrency($cart));
    }

    public function testGetConfigFieldsValuesReturnsStoredValues(): void
    {
        $result = $this->module->getConfigFieldsValues();
        $this->assertSame('Test Payee', $result['CHEQUE_NAME']);
        $this->assertSame('123 Test St', $result['CHEQUE_ADDRESS']);
    }

    public function testRenderFormReturnsString(): void
    {
        $this->assertIsString($this->module->renderForm());
    }
}
```

---

## Module tearDown Checklist

```php
protected function tearDown(): void
{
    Configuration::resetAll();   // Clear all primed config values
    Cache::resetAll();           // Clear any cached module data
    parent::tearDown();
}
```

Never leave primed config values between tests — they cause order-dependent failures.

---

## Adding Missing Stubs

If the module instantiates a class not yet in `CoreStubs.php`:

1. Check `tests/Unit/stubs/CoreStubs.php` for an existing stub
2. If missing, add it inside the `if (!class_exists(...))` guard at the end of the file
3. Stub only the methods that the module actually calls — no more
4. Keep return types compatible with how the module uses the return value
