<?php
/**
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License version 3.0
 * that is bundled with this package in the file LICENSE.md
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/license/osl-3-0-php
 *
 * @author    Webkul IN
 * @copyright Since 2010 Webkul
 * @license   https://opensource.org/license/osl-3-0-php Open Software License version 3.0
 */

// ── Constants ─────────────────────────────────────────────────────────────────
if (!defined('_DB_PREFIX_'))        define('_DB_PREFIX_', 'ps_');
if (!defined('_PS_USE_SQL_SLAVE_')) define('_PS_USE_SQL_SLAVE_', 1);
if (!defined('_PS_MODE_DEV_'))      define('_PS_MODE_DEV_', false);
if (!defined('_PS_MAIL_DIR_'))      define('_PS_MAIL_DIR_', '/tmp/');

// ── Global function stubs ─────────────────────────────────────────────────────
if (!function_exists('pSQL')) {
    function pSQL(string $string, bool $htmlOK = false): string
    {
        return addslashes($string);
    }
}
if (!function_exists('bqSQL')) {
    function bqSQL(string $string): string
    {
        return addslashes($string);
    }
}

// ── Class stubs (must be defined before Customer.php is loaded) ───────────────
if (!class_exists('PrestaShopException')) {
    class PrestaShopException extends RuntimeException {}
}
if (!class_exists('PrestaShopDatabaseException')) {
    class PrestaShopDatabaseException extends PrestaShopException {}
}

if (!class_exists('ObjectModel')) {
    class ObjectModel
    {
        const TYPE_INT     = 1;
        const TYPE_BOOL    = 2;
        const TYPE_STRING  = 3;
        const TYPE_FLOAT   = 4;
        const TYPE_DATE    = 5;
        const TYPE_HTML    = 6;
        const TYPE_NOTHING = 7;
        const INSERT_IGNORE = 'INSERT IGNORE';

        public static $definition = [];
        public $id = null;

        public function __construct($id = null) { $this->id = $id; }
        public function add($autodate = true, $null_values = true) { return true; }
        public function update($null_values = false) { return true; }
        public function delete() { return true; }
        public function save() { return true; }
        public function validateFields($die = true, $error_return = false) { return true; }
        public function toggleStatus() { return true; }
        public function getWebserviceObjectList($sql_join, $sql_filter, $sql_sort, $sql_limit) { return []; }
    }
}

if (!class_exists('Db')) {
    class Db
    {
        const INSERT_IGNORE  = 'INSERT IGNORE';
        const INSERT_ON_DUPLICATE_KEY = 'INSERT ON DUPLICATE KEY';
        const REPLACE = 'REPLACE';

        private static ?self $instance = null;

        public static function getInstance($slave = 0): static
        {
            if (!static::$instance) {
                static::$instance = new static();
            }
            return static::$instance;
        }

        public function getRow($sql): array|false { return false; }
        public function getValue($sql): string|false { return false; }
        public function executeS($sql): array|false { return []; }
        public function execute($sql): bool { return true; }
        public function insert(string $table, array $data, bool $null_values = false, bool $use_cache = true, $type = null): bool { return true; }
        public function delete(string $table, string $where = '', int $limit = 0): bool { return true; }
        public function Insert_ID(): int { return 0; }
    }
}

if (!class_exists('Context')) {
    class Context
    {
        private static ?self $instance = null;
        public $shop;
        public $language;
        public $cookie;
        public $controller;
        public $cart;

        public static function getContext(): static
        {
            if (!static::$instance) {
                static::$instance = new static();
            }
            return static::$instance;
        }
    }
}

if (!class_exists('Configuration')) {
    class Configuration
    {
        private static array $values = [];

        public static function get(string $key, $id_lang = null, $id_shop_group = null, $id_shop = null, $default = false): mixed
        {
            return static::$values[$key] ?? $default;
        }

        public static function set(string $key, $value): void
        {
            static::$values[$key] = $value;
        }

        public static function resetAll(): void
        {
            static::$values = [];
        }
    }
}

if (!class_exists('Validate')) {
    class Validate
    {
        public static function isEmail(string $email): bool
        {
            return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
        }
        public static function isPasswd(string $passwd): bool { return strlen($passwd) >= 5; }
        public static function isUnsignedId($id): bool { return is_numeric($id) && (int) $id > 0; }
        public static function isMd5(string $str): bool { return (bool) preg_match('/^[0-9a-f]{32}$/i', $str); }
        public static function isPhoneNumber(string $phone): bool { return strlen(trim($phone)) >= 7; }
        public static function isDate(string $date): bool { return (bool) strtotime($date); }
        public static function isUnsignedInt($n): bool { return is_numeric($n) && (int) $n >= 0; }
        public static function isGenericName(string $s): bool { return strlen($s) > 0 && !preg_match('/<[^>]+>/', $s); }
        public static function isName(string $s): bool { return strlen($s) > 0 && strlen($s) <= 32; }
        public static function isUrl(string $url): bool { return (bool) filter_var($url, FILTER_VALIDATE_URL); }
        public static function isSiret(string $s): bool { return (bool) preg_match('/^[0-9]{14}$/', $s); }
        public static function isApe(string $s): bool { return strlen($s) > 0; }
        public static function isFloat($n): bool { return is_numeric($n); }
        public static function isBool($b): bool { return in_array($b, [0, 1, '0', '1', true, false], true) || is_bool($b); }
        public static function isCleanHtml(string $s): bool { return true; }
        public static function isBirthDate(string $s): bool { return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $s); }
    }
}

if (!class_exists('Tools')) {
    class Tools
    {
        public static function displayError(string $message = ''): string { return $message; }
        public static function encrypt(string $passwd): string { return md5($passwd); }
        public static function passwdGen(int $length = 8, string $type = 'RANDOM'): string { return str_repeat('a', $length); }
        public static function displayAsDeprecated(): void {}
    }
}

if (!class_exists('Shop')) {
    class Shop
    {
        const SHARE_CUSTOMER = 1;
        public static function addSqlRestriction($type = null, $alias = null): string { return ''; }
        public static function addSqlAssociation(string $table, string $alias): string { return ''; }
    }
}

if (!class_exists('Cache')) {
    class Cache
    {
        private static array $store = [];
        public static function isStored(string $key): bool { return array_key_exists($key, static::$store); }
        public static function store(string $key, $value): void { static::$store[$key] = $value; }
        public static function retrieve(string $key): mixed { return static::$store[$key] ?? null; }
        public static function clean(string $key): void { unset(static::$store[$key]); }
        public static function resetAll(): void { static::$store = []; }
    }
}

if (!class_exists('Group')) {
    class Group
    {
        public static function isFeatureActive(): bool { return true; }
    }
}

if (!class_exists('Order')) {
    class Order
    {
        public static function getCustomerOrders(int $id_customer): array { return []; }
    }
}

if (!class_exists('Address')) {
    class Address
    {
        public function __construct(int $id = 0) {}
        public function delete(): bool { return true; }
        public static function getCountryAndState(int $id_address): array { return []; }
    }
}

if (!class_exists('CartRule')) {
    class CartRule
    {
        public static function deleteByIdCustomer(int $id_customer): bool { return true; }
    }
}

if (!class_exists('HotelCartBookingData')) {
    class HotelCartBookingData
    {
        public function deleteCartBookingData($id_cart, $a, $b, $c, $d, $e): bool { return true; }
    }
}

if (!class_exists('CustomerGuestDetail')) {
    class CustomerGuestDetail
    {
        public function deleteCustomerGuestByIdCustomer(int $id_customer): bool { return true; }
    }
}

if (!class_exists('Cart')) {
    class Cart
    {
        public int $id = 0;
        public function __construct(int $id = 0) { $this->id = $id; }
        public static function getCustomerCarts(int $id_customer, bool $with_order = true): array { return []; }
        public function nbProducts(): int { return 0; }
    }
}

if (!class_exists('Mail')) {
    class Mail
    {
        public static function Send($id_lang, $template, $subject, $vars, $to, $to_name, ...$rest): bool { return true; }
        public static function l(string $string, int $id_lang = null): string { return $string; }
    }
}

if (!class_exists('Hook')) {
    class Hook
    {
        public static function exec(string $hook_name, array $hook_args = []): string { return ''; }
    }
}

if (!class_exists('DbQuery')) {
    class DbQuery
    {
        private array $parts = [];
        public function select(string $fields): static { $this->parts[] = 'SELECT '.$fields; return $this; }
        public function from(string $table, ?string $alias = null): static { $this->parts[] = 'FROM '.$table; return $this; }
        public function leftJoin(string $table, string $alias, string $on): static { $this->parts[] = 'LEFT JOIN '.$table; return $this; }
        public function where(string $where): static { $this->parts[] = 'WHERE '.$where; return $this; }
        public function groupBy(string $fields): static { $this->parts[] = 'GROUP BY '.$fields; return $this; }
        public function orderBy(string $fields): static { $this->parts[] = 'ORDER BY '.$fields; return $this; }
        public function build(): string { return implode(' ', $this->parts); }
    }
}

// ── Load class under test ─────────────────────────────────────────────────────
require_once dirname(__DIR__, 2) . '/classes/Customer.php';

if (!class_exists('Customer')) {
    class Customer extends CustomerCore {}
}

use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Default configuration values expected by Customer::__construct
        Configuration::set('PS_CUSTOMER_GROUP', 3);
        Configuration::set('PS_GUEST_GROUP', 2);
        Configuration::set('PS_UNIDENTIFIED_GROUP', 1);
        Configuration::set('PS_ONE_PHONE_AT_LEAST', false);
        Configuration::set('PS_PASSWD_TIME_FRONT', 360);
        Configuration::set('PS_GUEST_CHECKOUT_ENABLED', true);
        Configuration::set('PS_LANG_DEFAULT', 1);
        Configuration::set('PS_TAX_ADDRESS_TYPE', 'id_address_delivery');
        Configuration::set('PS_COUNTRY_DEFAULT', 1);

        // Inject Db mock into singleton
        $this->dbMock = $this->createMock(Db::class);
        $ref = new ReflectionProperty(Db::class, 'instance');
        $ref->setAccessible(true);
        $ref->setValue(null, $this->dbMock);

        // Clear CustomerCore static caches
        foreach (['_customer_groups', '_defaultGroupId', '_customerHasAddress'] as $prop) {
            $ref = new ReflectionProperty(CustomerCore::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null, []);
        }

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

        Configuration::resetAll();
        Cache::resetAll();

        // Reset phone 'required' flag — it's set statically by __construct when PS_ONE_PHONE_AT_LEAST=true
        unset(Customer::$definition['fields']['phone']['required']);

        // Clear CustomerCore static caches
        foreach (['_customer_groups', '_defaultGroupId', '_customerHasAddress'] as $prop) {
            $ref = new ReflectionProperty(CustomerCore::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null, []);
        }

        parent::tearDown();
    }

    // ── $definition structure ────────────────────────────────────────────────

    public function testDefinitionTableIsCustomer(): void
    {
        $this->assertSame('customer', Customer::$definition['table']);
    }

    public function testDefinitionPrimaryIsIdCustomer(): void
    {
        $this->assertSame('id_customer', Customer::$definition['primary']);
    }

    public function testDefinitionHasRequiredFields(): void
    {
        $fields = Customer::$definition['fields'];
        foreach (['email', 'lastname', 'firstname', 'passwd'] as $field) {
            $this->assertArrayHasKey($field, $fields, "Missing required field: $field");
            $this->assertTrue($fields[$field]['required'], "Field '$field' should be required");
        }
    }

    public function testDefinitionAllFieldsHaveValidTypes(): void
    {
        $validTypes = [
            ObjectModel::TYPE_INT, ObjectModel::TYPE_BOOL, ObjectModel::TYPE_STRING,
            ObjectModel::TYPE_FLOAT, ObjectModel::TYPE_DATE, ObjectModel::TYPE_HTML,
        ];
        foreach (Customer::$definition['fields'] as $field => $spec) {
            $this->assertArrayHasKey('type', $spec, "Field '$field' missing type");
            $this->assertContains($spec['type'], $validTypes, "Field '$field' has invalid type");
        }
    }

    public function testDefinitionEmailHasSizeLimit(): void
    {
        $this->assertSame(128, Customer::$definition['fields']['email']['size']);
    }

    public function testDefinitionLastnameHasSizeLimit(): void
    {
        $this->assertSame(32, Customer::$definition['fields']['lastname']['size']);
    }

    // ── Constructor ──────────────────────────────────────────────────────────

    public function testConstructorSetsDefaultGroupFromConfiguration(): void
    {
        Configuration::set('PS_CUSTOMER_GROUP', 7);
        $customer = new Customer();
        $this->assertSame(7, $customer->id_default_group);
    }

    public function testConstructorWithNullIdSetsNullId(): void
    {
        $customer = new Customer();
        $this->assertNull($customer->id);
    }

    public function testConstructorWithIdSetsId(): void
    {
        $customer = new Customer(42);
        $this->assertSame(42, $customer->id);
    }

    public function testConstructorMakesPhoneRequiredWhenConfigEnabled(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', true);
        new Customer();
        $this->assertTrue(Customer::$definition['fields']['phone']['required']);
    }

    public function testConstructorDoesNotMakePhoneRequiredWhenConfigDisabled(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', false);
        new Customer();
        $this->assertArrayNotHasKey('required', Customer::$definition['fields']['phone']);
    }

    // ── isGuest() ────────────────────────────────────────────────────────────

    public function testIsGuestReturnsTrueWhenFlagIsOne(): void
    {
        $customer = new Customer();
        $customer->is_guest = 1;
        $this->assertTrue($customer->isGuest());
    }

    public function testIsGuestReturnsFalseWhenFlagIsZero(): void
    {
        $customer = new Customer();
        $customer->is_guest = 0;
        $this->assertFalse($customer->isGuest());
    }

    // ── isLogged() ───────────────────────────────────────────────────────────

    public function testIsLoggedReturnsFalseWhenNotLoggedIn(): void
    {
        $customer = new Customer();
        $customer->logged = 0;
        $customer->id = 1;
        $this->assertFalse($customer->isLogged());
    }

    public function testIsLoggedReturnsFalseWhenIdIsZero(): void
    {
        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 0;
        $this->assertFalse($customer->isLogged());
    }

    public function testIsLoggedReturnsFalseForGuestWithoutGuestFlag(): void
    {
        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->logged = 1;
        $customer->id = 1;
        $customer->passwd = md5('pass');
        $this->assertFalse($customer->isLogged(false));
    }

    public function testIsLoggedReturnsTrueWhenLoggedInWithValidPassword(): void
    {
        $passwd = md5('secret');
        // Pre-populate the Cache so checkPassword returns true without hitting DB
        Cache::store('Customer::checkPassword1-'.$passwd, true);

        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 1;
        $customer->passwd = $passwd;

        $this->assertTrue($customer->isLogged());
    }

    // ── isUsed() (deprecated) ────────────────────────────────────────────────

    public function testIsUsedAlwaysReturnsFalse(): void
    {
        $customer = new Customer();
        $this->assertFalse($customer->isUsed());
    }

    // ── customerExists() ────────────────────────────────────────────────────

    public function testCustomerExistsReturnsFalseForInvalidEmail(): void
    {
        $this->assertFalse(Customer::customerExists('not-an-email'));
    }

    public function testCustomerExistsReturnsFalseWhenNoRowFound(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);
        $this->assertFalse(Customer::customerExists('user@example.com'));
    }

    public function testCustomerExistsReturnsTrueWhenRowFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('4');
        $this->assertTrue(Customer::customerExists('user@example.com'));
    }

    public function testCustomerExistsReturnsIdWhenReturnIdTrue(): void
    {
        $this->dbMock->method('getValue')->willReturn('9');
        $this->assertSame(9, Customer::customerExists('user@example.com', true));
    }

    public function testCustomerExistsReturnsZeroIdWhenNotFoundAndReturnIdTrue(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);
        $this->assertSame(0, Customer::customerExists('user@example.com', true));
    }

    public function testCustomerExistsQueriesDbWithEmailAndGuestFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('getValue')
            ->with($this->stringContains('is_guest'))
            ->willReturn(false);

        Customer::customerExists('user@example.com', false, true);
    }

    // ── customerHasAddress() ────────────────────────────────────────────────

    public function testCustomerHasAddressReturnsTrueWhenFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('5');
        $this->assertTrue(Customer::customerHasAddress(1, 5));
    }

    public function testCustomerHasAddressReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);
        $this->assertFalse(Customer::customerHasAddress(1, 99));
    }

    public function testCustomerHasAddressHitsDbOnlyOnceForSamePair(): void
    {
        $this->dbMock->expects($this->once())->method('getValue')->willReturn('5');
        Customer::customerHasAddress(1, 5);
        Customer::customerHasAddress(1, 5); // second call must use static cache
    }

    public function testCustomerHasAddressDifferentPairsHitDbSeparately(): void
    {
        $this->dbMock->expects($this->exactly(2))->method('getValue')->willReturn('1');
        Customer::customerHasAddress(1, 10);
        Customer::customerHasAddress(1, 11); // different pair → different cache key
    }

    // ── resetAddressCache() ──────────────────────────────────────────────────

    public function testResetAddressCacheForcesDbQueryAfterReset(): void
    {
        $this->dbMock
            ->expects($this->exactly(2))
            ->method('getValue')
            ->willReturnOnConsecutiveCalls('5', false);

        Customer::customerHasAddress(2, 10);     // populates cache
        Customer::resetAddressCache(2, 10);      // clears it
        $result = Customer::customerHasAddress(2, 10); // must hit DB again

        $this->assertFalse($result);
    }

    public function testResetAddressCacheOnNonExistentKeyDoesNotThrow(): void
    {
        Customer::resetAddressCache(999, 999); // should not throw
        $this->assertTrue(true);
    }

    // ── getLastEmails() ──────────────────────────────────────────────────────

    public function testGetLastEmailsReturnsEmptyArrayWhenIdIsNull(): void
    {
        $customer = new Customer();
        $this->assertSame([], $customer->getLastEmails());
    }

    public function testGetLastEmailsQueriesDbAndReturnsResultsWhenIdSet(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->willReturn([['id_mail' => 1, 'subject' => 'Welcome']]);

        $customer = new Customer();
        $customer->id = 5;
        $customer->email = 'user@example.com';

        $result = $customer->getLastEmails();
        $this->assertCount(1, $result);
        $this->assertSame('Welcome', $result[0]['subject']);
    }

    public function testGetLastEmailsReturnsEmptyResultFromDb(): void
    {
        $this->dbMock->method('executeS')->willReturn([]);

        $customer = new Customer();
        $customer->id = 5;
        $customer->email = 'user@example.com';

        $this->assertSame([], $customer->getLastEmails());
    }

    // ── getLastConnections() ─────────────────────────────────────────────────

    public function testGetLastConnectionsReturnsEmptyArrayWhenIdIsNull(): void
    {
        $customer = new Customer();
        $this->assertSame([], $customer->getLastConnections());
    }

    public function testGetLastConnectionsQueriesDbWhenIdIsSet(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->willReturn([['id_connections' => 1, 'date_add' => '2024-01-01 10:00:00']]);

        $customer = new Customer();
        $customer->id = 3;

        $result = $customer->getLastConnections();
        $this->assertCount(1, $result);
    }

    // ── updateGroup() ────────────────────────────────────────────────────────

    public function testUpdateGroupWithListCallsCleanAndAdd(): void
    {
        $this->dbMock->expects($this->once())->method('delete')->willReturn(true);
        $this->dbMock->expects($this->exactly(2))->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->id = 1;
        $customer->updateGroup([10, 20]);
    }

    public function testUpdateGroupWithEmptyListFallsBackToDefaultGroup(): void
    {
        $customer = new Customer();
        $customer->id = 1;
        $customer->id_default_group = 3;

        // else branch: only addGroups() is called, cleanGroups() is NOT
        $this->dbMock->expects($this->never())->method('delete');
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with('customer_group', $this->callback(fn($data) => $data['id_group'] === 3))
            ->willReturn(true);

        $customer->updateGroup([]);
    }

    public function testUpdateGroupWithNullAlsoFallsBackToDefaultGroup(): void
    {
        $customer = new Customer();
        $customer->id = 1;
        $customer->id_default_group = 5;

        // else branch: only addGroups() is called, cleanGroups() is NOT
        $this->dbMock->expects($this->never())->method('delete');
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with('customer_group', $this->callback(fn($data) => $data['id_group'] === 5))
            ->willReturn(true);

        $customer->updateGroup(null);
    }

    // ── cleanGroups() ────────────────────────────────────────────────────────

    public function testCleanGroupsCallsDbDeleteOnCustomerGroup(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('delete')
            ->with('customer_group', $this->stringContains('id_customer'))
            ->willReturn(true);

        $customer = new Customer();
        $customer->id = 7;
        $customer->cleanGroups();
    }

    public function testCleanGroupsReturnsFalseWhenDbFails(): void
    {
        $this->dbMock->method('delete')->willReturn(false);

        $customer = new Customer();
        $customer->id = 7;
        $this->assertFalse($customer->cleanGroups());
    }

    // ── addGroups() ──────────────────────────────────────────────────────────

    public function testAddGroupsInsertsEachGroup(): void
    {
        $this->dbMock->expects($this->exactly(3))->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->id = 1;
        $customer->addGroups([2, 3, 4]);
    }

    public function testAddGroupsWithEmptyListDoesNotCallInsert(): void
    {
        $this->dbMock->expects($this->never())->method('insert');

        $customer = new Customer();
        $customer->id = 1;
        $customer->addGroups([]);
    }

    // ── setWsPasswd() ────────────────────────────────────────────────────────

    public function testSetWsPasswdEncryptsWhenIdIsZero(): void
    {
        $customer = new Customer();
        $customer->id = 0;
        $customer->passwd = '';

        $customer->setWsPasswd('mypassword');

        $this->assertSame(md5('mypassword'), $customer->passwd);
    }

    public function testSetWsPasswdEncryptsWhenPasswordDiffers(): void
    {
        $customer = new Customer();
        $customer->id = 5;
        $customer->passwd = md5('oldpass');

        $customer->setWsPasswd('newpass');

        $this->assertSame(md5('newpass'), $customer->passwd);
    }

    public function testSetWsPasswdDoesNotChangeWhenSameEncryptedPassProvided(): void
    {
        $encrypted = md5('samepass');
        $customer = new Customer();
        $customer->id = 5;
        $customer->passwd = $encrypted;

        $customer->setWsPasswd($encrypted);

        $this->assertSame($encrypted, $customer->passwd);
    }

    public function testSetWsPasswdAlwaysReturnsTrue(): void
    {
        $customer = new Customer();
        $customer->id = 1;
        $customer->passwd = 'anything';

        $this->assertTrue($customer->setWsPasswd('newvalue'));
    }

    // ── transformToCustomer() ────────────────────────────────────────────────

    public function testTransformToCustomerReturnsFalseWhenNotAGuest(): void
    {
        $customer = new Customer();
        $customer->is_guest = 0;

        $this->assertFalse($customer->transformToCustomer(1, 'validpass'));
    }

    public function testTransformToCustomerReturnsFalseForTooShortPassword(): void
    {
        $customer = new Customer();
        $customer->is_guest = 1;

        $this->assertFalse($customer->transformToCustomer(1, 'ab')); // < 5 chars
    }

    public function testTransformToCustomerReturnsTrueAndConvertsGuest(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 10;
        $customer->firstname = 'John';
        $customer->lastname = 'Doe';
        $customer->email = 'john@example.com';

        $result = $customer->transformToCustomer(1, 'validpassword');

        $this->assertTrue($result);
        $this->assertSame(0, $customer->is_guest);
    }

    public function testTransformToCustomerEncryptsProvidedPassword(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 10;
        $customer->firstname = 'Jane';
        $customer->lastname = 'Doe';
        $customer->email = 'jane@example.com';

        $customer->transformToCustomer(1, 'mysecret');

        $this->assertSame(md5('mysecret'), $customer->passwd);
    }

    public function testTransformToCustomerSetsDefaultCustomerGroup(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);
        Configuration::set('PS_CUSTOMER_GROUP', 3);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 10;
        $customer->firstname = 'Alice';
        $customer->lastname = 'Smith';
        $customer->email = 'alice@example.com';

        $customer->transformToCustomer(1, 'validpassword');

        $this->assertSame(3, $customer->id_default_group);
    }

    // ── getGroupsStatic() ────────────────────────────────────────────────────

    public function testGetGroupsStaticReturnsUnidentifiedGroupForIdZero(): void
    {
        Configuration::set('PS_UNIDENTIFIED_GROUP', 1);
        $result = Customer::getGroupsStatic(0);
        $this->assertContains(1, $result);
    }

    public function testGetGroupsStaticQueriesDbAndReturnsGroups(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->willReturn([['id_group' => 3], ['id_group' => 5]]);

        $result = Customer::getGroupsStatic(7);

        $this->assertContains(3, $result);
        $this->assertContains(5, $result);
    }

    public function testGetGroupsStaticUsesStaticCacheOnSecondCall(): void
    {
        $this->dbMock->expects($this->once())->method('executeS')->willReturn([['id_group' => 3]]);

        Customer::getGroupsStatic(8);
        Customer::getGroupsStatic(8); // second call must NOT hit DB
    }

    public function testGetGroupsStaticReturnsEmptyArrayWhenNoGroupsFound(): void
    {
        $this->dbMock->method('executeS')->willReturn([]);
        $result = Customer::getGroupsStatic(99);
        $this->assertSame([], $result);
    }

    // ── getCustomers() ───────────────────────────────────────────────────────

    public function testGetCustomersReturnsDbResults(): void
    {
        $this->dbMock->method('executeS')->willReturn([['id_customer' => 1, 'email' => 'a@b.com']]);
        $result = Customer::getCustomers();
        $this->assertCount(1, $result);
    }

    public function testGetCustomersWithActiveFilterIncludesActiveInQuery(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('active'))
            ->willReturn([]);

        Customer::getCustomers(1);
    }

    public function testGetCustomersWithDeletedFilterIncludesDeletedInQuery(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('deleted'))
            ->willReturn([]);

        Customer::getCustomers(null, 0);
    }

    public function testGetCustomersWithHavingAddressJoinsAddressTable(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('address'))
            ->willReturn([]);

        Customer::getCustomers(null, null, true);
    }

    // ── searchByName() ───────────────────────────────────────────────────────

    public function testSearchByNameWithLimitAppendsLimitClause(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('LIMIT'))
            ->willReturn([]);

        Customer::searchByName('john', 10);
    }

    public function testSearchByNameWithoutLimitOmitsLimitClause(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->logicalNot($this->stringContains('LIMIT')))
            ->willReturn([]);

        Customer::searchByName('john');
    }

    public function testSearchByNameSkipDeletedAddsDeletedFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('deleted'))
            ->willReturn([]);

        Customer::searchByName('john', null, true);
    }

    public function testSearchByNameReturnsDbResults(): void
    {
        $this->dbMock->method('executeS')->willReturn([['id_customer' => 1, 'email' => 'john@test.com']]);
        $result = Customer::searchByName('john');
        $this->assertCount(1, $result);
    }

    // ── validateFields() ─────────────────────────────────────────────────────

    public function testValidateFieldsCallsParentWhenWebserviceValidationNotSet(): void
    {
        $customer = new Customer();
        // No webservice_validation property → parent::validateFields() from stub returns true
        $this->assertTrue($customer->validateFields(false));
    }

    public function testValidateFieldsThrowsExceptionWhenPhoneRequiredAndMissing(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', true);

        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = '';

        $this->expectException(PrestaShopException::class);
        $customer->validateFields(true);
    }

    public function testValidateFieldsReturnsFalseWhenPhoneRequiredMissingAndDieIsFalse(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', true);

        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = '';

        $this->assertFalse($customer->validateFields(false, false));
    }

    public function testValidateFieldsReturnsErrorMessageStringWhenErrorReturnIsTrue(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', true);

        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = '';

        $result = $customer->validateFields(false, true);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testValidateFieldsPassesWhenPhoneProvidedAndRequired(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', true);

        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = '+1234567890';

        $this->assertTrue($customer->validateFields(false));
    }

    // ── Field validation (data-driven) ───────────────────────────────────────

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
            'valid with subdomain' => ['user@mail.example.com', true],
            'missing at sign'      => ['userexample.com', false],
            'missing domain'       => ['user@', false],
            'empty string'         => ['', false],
        ];
    }

    /**
     * @dataProvider providePasswordValidation
     */
    public function testPasswordFieldValidation(string $passwd, bool $expected): void
    {
        $this->assertSame($expected, Validate::isPasswd($passwd));
    }

    public function providePasswordValidation(): array
    {
        return [
            'valid 8 chars'   => ['password', true],
            'exactly 5 chars' => ['abcde', true],
            'too short 4'     => ['abcd', false],
            'too short 2'     => ['ab', false],
            'empty'           => ['', false],
        ];
    }

    /**
     * @dataProvider provideBirthDateValidation
     */
    public function testBirthdayFieldValidation(string $date, bool $expected): void
    {
        $this->assertSame($expected, Validate::isBirthDate($date));
    }

    public function provideBirthDateValidation(): array
    {
        return [
            'valid date'       => ['1990-06-15', true],
            'valid boundary'   => ['2000-01-01', true],
            'missing day'      => ['1990-06', false],
            'wrong format'     => ['15/06/1990', false],
            'empty'            => ['', false],
        ];
    }

    // ── STATUS constants ──────────────────────────────────────────────────────

    public function testStatusBannedConstantValue(): void
    {
        $this->assertSame(1, Customer::STATUS_BANNED);
    }

    public function testStatusDeletedConstantValue(): void
    {
        $this->assertSame(2, Customer::STATUS_DELETED);
    }
}
