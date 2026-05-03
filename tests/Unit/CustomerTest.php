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

class CustomerTest extends TestCase
{
    private $dbMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dbMock = $this->createMock(Db::class);
        $this->dbMock->method('escape')->willReturnArgument(0);
        $dbRef = new ReflectionProperty(Db::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, $this->dbMock);

        $ctxRef = new ReflectionProperty(Context::class, 'instance');
        $ctxRef->setAccessible(true);
        $ctx = new Context();
        $ctx->shop = new class {
            public int $id = 1;
            public int $id_shop_group = 1;
            public function getGroup(): object {
                return (object)['share_order' => false];
            }
        };
        $ctx->language = new class { public int $id = 1; };
        $ctxRef->setValue(null, $ctx);

        Configuration::set('PS_CUSTOMER_GROUP', 3);
        Configuration::set('PS_GUEST_GROUP', 2);
        Configuration::set('PS_UNIDENTIFIED_GROUP', 4);
        Configuration::set('PS_LANG_DEFAULT', 1);
        Configuration::set('PS_PASSWD_TIME_FRONT', 10);
        Configuration::set('PS_GUEST_CHECKOUT_ENABLED', 1);
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 0);

        $this->resetStaticCaches();
    }

    protected function tearDown(): void
    {
        $dbRef = new ReflectionProperty(Db::class, 'instance');
        $dbRef->setAccessible(true);
        $dbRef->setValue(null, null);

        $ctxRef = new ReflectionProperty(Context::class, 'instance');
        $ctxRef->setAccessible(true);
        $ctxRef->setValue(null, null);

        ObjectModel::$updateResult = true;
        Group::setFeatureActive(true);

        if (isset(Customer::$definition['fields']['phone']['required'])) {
            unset(Customer::$definition['fields']['phone']['required']);
        }

        Configuration::resetAll();
        Cache::resetAll();
        Hook::$hookReturn = '';

        $this->resetStaticCaches();
        parent::tearDown();
    }

    private function resetStaticCaches(): void
    {
        foreach (['_defaultGroupId', '_customerHasAddress', '_customer_groups'] as $prop) {
            $ref = new ReflectionProperty(Customer::class, $prop);
            $ref->setAccessible(true);
            $ref->setValue(null, []);
        }
    }

    // ── $definition ──────────────────────────────────────────────────────────────

    public function testDefinitionTableIsCustomer(): void
    {
        $this->assertSame('customer', Customer::$definition['table']);
    }

    public function testDefinitionPrimaryIsIdCustomer(): void
    {
        $this->assertSame('id_customer', Customer::$definition['primary']);
    }

    public function testDefinitionRequiredFieldsExist(): void
    {
        foreach (['lastname', 'firstname', 'email', 'passwd'] as $field) {
            $this->assertTrue(
                Customer::$definition['fields'][$field]['required'] ?? false,
                "Field '$field' should be required"
            );
        }
    }

    public function testDefinitionFieldsHaveValidTypes(): void
    {
        $validTypes = [
            ObjectModel::TYPE_INT, ObjectModel::TYPE_BOOL, ObjectModel::TYPE_STRING,
            ObjectModel::TYPE_FLOAT, ObjectModel::TYPE_DATE, ObjectModel::TYPE_HTML,
        ];
        foreach (Customer::$definition['fields'] as $field => $spec) {
            $this->assertContains($spec['type'], $validTypes, "Field '$field' has invalid type");
        }
    }

    public function testDefinitionHasPhoneField(): void
    {
        $this->assertArrayHasKey('phone', Customer::$definition['fields']);
        $this->assertSame(ObjectModel::TYPE_STRING, Customer::$definition['fields']['phone']['type']);
    }

    // ── __construct ──────────────────────────────────────────────────────────────

    public function testConstructorSetsDefaultGroupFromConfiguration(): void
    {
        $customer = new Customer();
        $this->assertSame(3, $customer->id_default_group);
    }

    public function testConstructorWithIdRetainsId(): void
    {
        $customer = new Customer(42);
        $this->assertSame(42, $customer->id);
    }

    public function testConstructorMarksPhoneRequiredWhenConfigEnabled(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 1);
        new Customer();
        $this->assertTrue(Customer::$definition['fields']['phone']['required'] ?? false);
    }

    public function testConstructorDoesNotMarkPhoneRequiredWhenConfigDisabled(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 0);
        new Customer();
        $this->assertFalse(Customer::$definition['fields']['phone']['required'] ?? false);
    }

    // ── validateFields ───────────────────────────────────────────────────────────

    public function testValidateFieldsCallsParentWhenNoWebserviceValidation(): void
    {
        $customer = new Customer();
        $this->assertTrue($customer->validateFields());
    }

    public function testValidateFieldsThrowsWhenPhoneRequiredAndMissing(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 1);
        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = null;

        $this->expectException(PrestaShopException::class);
        $customer->validateFields(true, false);
    }

    public function testValidateFieldsThrowsWhenPhoneRequiredAndInvalid(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 1);
        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = 'not-a-phone';

        $this->expectException(PrestaShopException::class);
        $customer->validateFields(true, false);
    }

    public function testValidateFieldsReturnsErrorMessageWhenDieFalseAndErrorReturnTrue(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 1);
        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = null;

        $result = $customer->validateFields(false, true);
        $this->assertIsString($result);
        $this->assertNotEmpty($result);
    }

    public function testValidateFieldsReturnsFalseWhenDieFalseAndErrorReturnFalse(): void
    {
        Configuration::set('PS_ONE_PHONE_AT_LEAST', 1);
        $customer = new Customer();
        $customer->webservice_validation = true;
        $customer->phone = null;

        $result = $customer->validateFields(false, false);
        $this->assertFalse($result);
    }

    // ── add() ────────────────────────────────────────────────────────────────────

    public function testAddReturnsFalseWhenGuestCheckoutDisabledForGuestCustomer(): void
    {
        Configuration::set('PS_GUEST_CHECKOUT_ENABLED', 0);
        $customer = new Customer();
        $customer->is_guest = 1;

        $this->assertFalse($customer->add());
    }

    public function testAddSetsSecureKey(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->add();

        $this->assertNotEmpty($customer->secure_key);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $customer->secure_key);
    }

    public function testAddSetsLastPasswdGen(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->add();

        $this->assertNotEmpty($customer->last_passwd_gen);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $customer->last_passwd_gen);
    }

    public function testAddSetsNewsletterDateAddWhenNewsletterAndNoDate(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->newsletter = 1;
        $customer->newsletter_date_add = null;
        $customer->add();

        $this->assertNotEmpty($customer->newsletter_date_add);
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}/', $customer->newsletter_date_add);
    }

    public function testAddSetsGuestGroupWhenIsGuest(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->add();

        $this->assertSame(2, $customer->id_default_group);
    }

    public function testAddSetsCustomerGroupWhenNotGuest(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->is_guest = 0;
        $customer->add();

        $this->assertSame(3, $customer->id_default_group);
    }

    public function testAddSetsIdFromInsertId(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(99);

        $customer = new Customer();
        $customer->add();

        $this->assertSame(99, $customer->id);
    }

    public function testAddPreservesExistingShopId(): void
    {
        $this->dbMock->method('insert')->willReturn(true);
        $this->dbMock->method('Insert_ID')->willReturn(1);

        $customer = new Customer();
        $customer->id_shop = 5;
        $customer->add();

        $this->assertSame(5, $customer->id_shop);
    }

    // ── checkPassword ────────────────────────────────────────────────────────────

    public function testCheckPasswordReturnsTrueWhenDbReturnsRow(): void
    {
        $passwd = Tools::encrypt('secret');
        $this->dbMock->method('getValue')->willReturn('1');

        $this->assertTrue(Customer::checkPassword(1, $passwd));
    }

    public function testCheckPasswordReturnsFalseWhenDbReturnsNothing(): void
    {
        $passwd = Tools::encrypt('wrong');
        $this->dbMock->method('getValue')->willReturn(false);

        $this->assertFalse(Customer::checkPassword(1, $passwd));
    }

    public function testCheckPasswordCachesResultOnSecondCall(): void
    {
        $passwd = Tools::encrypt('secret');
        $this->dbMock->expects($this->once())->method('getValue')->willReturn('1');

        Customer::checkPassword(1, $passwd);
        $result = Customer::checkPassword(1, $passwd);
        $this->assertTrue($result);
    }

    // ── isLogged ─────────────────────────────────────────────────────────────────

    public function testIsLoggedReturnsFalseWhenNotLogged(): void
    {
        $customer = new Customer();
        $customer->logged = 0;
        $customer->id = 5;

        $this->assertFalse($customer->isLogged());
    }

    public function testIsLoggedReturnsFalseWhenIdIsZero(): void
    {
        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 0;

        $this->assertFalse($customer->isLogged());
    }

    public function testIsLoggedReturnsFalseForGuestWithoutFlag(): void
    {
        $passwd = Tools::encrypt('secret');
        Cache::store('Customer::checkPassword5-' . $passwd, true);

        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 5;
        $customer->is_guest = 1;
        $customer->passwd = $passwd;

        $this->assertFalse($customer->isLogged());
    }

    public function testIsLoggedReturnsTrueForValidLoggedCustomer(): void
    {
        $passwd = Tools::encrypt('secret');
        Cache::store('Customer::checkPassword5-' . $passwd, true);

        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 5;
        $customer->is_guest = 0;
        $customer->passwd = $passwd;

        $this->assertTrue($customer->isLogged());
    }

    public function testIsLoggedReturnsTrueForGuestWithFlag(): void
    {
        $passwd = Tools::encrypt('secret');
        Cache::store('Customer::checkPassword5-' . $passwd, true);

        $customer = new Customer();
        $customer->logged = 1;
        $customer->id = 5;
        $customer->is_guest = 1;
        $customer->passwd = $passwd;

        $this->assertTrue($customer->isLogged(true));
    }

    // ── customerExists ───────────────────────────────────────────────────────────

    #[DataProvider('provideInvalidEmails')]
    public function testCustomerExistsReturnsFalseForInvalidEmail(string $email): void
    {
        $this->assertFalse(Customer::customerExists($email));
    }

    public static function provideInvalidEmails(): array
    {
        return [
            'empty string'  => [''],
            'no at sign'    => ['notanemail'],
            'no domain'     => ['user@'],
            'double at'     => ['user@@example.com'],
            'spaces inside' => ['user @example.com'],
        ];
    }

    public function testCustomerExistsReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);
        $this->assertFalse(Customer::customerExists('user@example.com'));
    }

    public function testCustomerExistsReturnsTrueWhenFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('5');
        $this->assertTrue(Customer::customerExists('user@example.com'));
    }

    public function testCustomerExistsReturnsIdWhenFoundAndReturnIdTrue(): void
    {
        $this->dbMock->method('getValue')->willReturn('5');
        $this->assertSame(5, Customer::customerExists('user@example.com', true));
    }

    public function testCustomerExistsFiltersGuestByDefault(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('getValue')
            ->with($this->stringContains('is_guest'))
            ->willReturn(false);

        $result = Customer::customerExists('user@example.com');
        $this->assertFalse($result);
    }

    // ── isBanned ─────────────────────────────────────────────────────────────────

    public function testIsBannedReturnsTrueForNegativeId(): void
    {
        $this->assertTrue(Customer::isBanned(-1));
    }

    public function testIsBannedReturnsFalseWhenActiveAndNotDeleted(): void
    {
        $this->dbMock->method('getRow')->willReturn(['id_customer' => 1]);
        $this->assertFalse(Customer::isBanned(1));
    }

    public function testIsBannedReturnsTrueWhenNotFound(): void
    {
        $this->dbMock->method('getRow')->willReturn(false);
        $this->assertTrue(Customer::isBanned(1));
    }

    public function testIsBannedCachesResult(): void
    {
        $this->dbMock->expects($this->once())->method('getRow')->willReturn(false);
        Customer::isBanned(1);
        $result = Customer::isBanned(1);
        $this->assertTrue($result);
    }

    // ── customerHasAddress + resetAddressCache ────────────────────────────────────

    public function testCustomerHasAddressReturnsTrueWhenFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('1');
        $this->assertTrue(Customer::customerHasAddress(1, 10));
    }

    public function testCustomerHasAddressReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);
        $this->assertFalse(Customer::customerHasAddress(1, 10));
    }

    public function testCustomerHasAddressCachesResult(): void
    {
        $this->dbMock->expects($this->once())->method('getValue')->willReturn('1');
        Customer::customerHasAddress(1, 10);
        $result = Customer::customerHasAddress(1, 10);
        $this->assertTrue($result);
    }

    public function testResetAddressCacheClearsEntry(): void
    {
        $this->dbMock->expects($this->exactly(2))->method('getValue')->willReturn('1', false);
        Customer::customerHasAddress(1, 10);
        Customer::resetAddressCache(1, 10);
        $result = Customer::customerHasAddress(1, 10);
        $this->assertFalse($result);
    }

    // ── getCustomers ─────────────────────────────────────────────────────────────

    public function testGetCustomersReturnsAllByDefault(): void
    {
        $rows = [['id_customer' => 1, 'email' => 'a@b.com', 'firstname' => 'A', 'lastname' => 'B']];
        $this->dbMock->method('executeS')->willReturn($rows);

        $this->assertSame($rows, Customer::getCustomers());
    }

    public function testGetCustomersFiltersActiveWhenActive1(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->logicalAnd(
                $this->stringContains('active'),
                $this->stringContains('= 1')
            ))
            ->willReturn([]);

        $result = Customer::getCustomers(true);
        $this->assertSame([], $result);
    }

    public function testGetCustomersFiltersDeletedWhenDeleted0(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->logicalAnd(
                $this->stringContains('deleted'),
                $this->stringContains('= 0')
            ))
            ->willReturn([]);

        $result = Customer::getCustomers(null, false);
        $this->assertSame([], $result);
    }

    public function testGetCustomersFiltersHavingAddressWithJoin(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->logicalAnd(
                $this->stringContains('address'),
                $this->stringContains('IS NOT NULL')
            ))
            ->willReturn([]);

        $result = Customer::getCustomers(null, null, true);
        $this->assertSame([], $result);
    }

    public function testGetCustomersFiltersNotHavingAddress(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('IS NULL'))
            ->willReturn([]);

        $result = Customer::getCustomers(null, null, false);
        $this->assertSame([], $result);
    }

    // ── getGroupsStatic ──────────────────────────────────────────────────────────

    public function testGetGroupsStaticReturnsCustomerGroupWhenFeatureInactive(): void
    {
        Group::setFeatureActive(false);
        $this->assertSame([3], Customer::getGroupsStatic(5));
    }

    public function testGetGroupsStaticReturnsUnidentifiedGroupForIdZero(): void
    {
        $this->assertSame([4], Customer::getGroupsStatic(0));
    }

    public function testGetGroupsStaticQueriesDbAndReturnsGroups(): void
    {
        $this->dbMock->method('executeS')->willReturn([['id_group' => 3], ['id_group' => 5]]);
        $this->assertSame([3, 5], Customer::getGroupsStatic(8));
    }

    public function testGetGroupsStaticCachesResult(): void
    {
        $this->dbMock->expects($this->once())->method('executeS')->willReturn([['id_group' => 3]]);
        Customer::getGroupsStatic(8);
        $result = Customer::getGroupsStatic(8);
        $this->assertSame([3], $result);
    }

    // ── getDefaultGroupId ────────────────────────────────────────────────────────

    public function testGetDefaultGroupIdReturnsConfigWhenFeatureInactive(): void
    {
        Group::setFeatureActive(false);
        $this->assertSame(3, Customer::getDefaultGroupId(5));
    }

    public function testGetDefaultGroupIdQueriesDb(): void
    {
        $this->dbMock->method('getValue')->willReturn('3');
        $this->assertSame('3', Customer::getDefaultGroupId(5));
    }

    public function testGetDefaultGroupIdCachesResult(): void
    {
        $this->dbMock->expects($this->once())->method('getValue')->willReturn('3');
        Customer::getDefaultGroupId(5);
        $result = Customer::getDefaultGroupId(5);
        $this->assertSame('3', $result);
    }

    // ── updateGroup / cleanGroups / addGroups ─────────────────────────────────────

    public function testUpdateGroupCallsCleanAndAddGroupsWhenListProvided(): void
    {
        $this->dbMock->expects($this->once())->method('delete')->willReturn(true);
        $this->dbMock->expects($this->once())->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $customer->updateGroup([7]);
        $this->assertSame(3, $customer->id_default_group);
    }

    public function testUpdateGroupAddsDefaultGroupWhenListEmpty(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('insert')
            ->with('customer_group', $this->callback(fn($data) => $data['id_group'] === 3))
            ->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $customer->updateGroup([]);
        $this->assertSame(3, $customer->id_default_group);
    }

    public function testCleanGroupsCallsDbDeleteWithCustomerId(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('delete')
            ->with('customer_group', $this->stringContains('5'))
            ->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $result = $customer->cleanGroups();
        $this->assertTrue($result);
    }

    public function testAddGroupsCallsDbInsertForEachGroup(): void
    {
        $this->dbMock->expects($this->exactly(2))->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $customer->addGroups([3, 7]);
        $this->assertSame(3, $customer->id_default_group);
    }

    // ── transformToCustomer ──────────────────────────────────────────────────────

    public function testTransformToCustomerReturnsFalseWhenNotGuest(): void
    {
        $customer = new Customer();
        $customer->is_guest = 0;
        $this->assertFalse($customer->transformToCustomer(1, 'validpass'));
    }

    public function testTransformToCustomerReturnsFalseForShortPassword(): void
    {
        $customer = new Customer();
        $customer->is_guest = 1;
        $this->assertFalse($customer->transformToCustomer(1, 'x'));
    }

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

    public function testTransformToCustomerEncryptsPassword(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 5;
        $customer->transformToCustomer(1, 'validpass');

        $this->assertSame(Tools::encrypt('validpass'), $customer->passwd);
    }

    public function testTransformToCustomerSetsCustomerGroup(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 5;
        $customer->transformToCustomer(1, 'validpass');

        $this->assertSame(3, $customer->id_default_group);
    }

    public function testTransformToCustomerReturnsFalseWhenUpdateFails(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);
        ObjectModel::$updateResult = false;

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 5;

        $this->assertFalse($customer->transformToCustomer(1, 'validpass'));
    }

    public function testTransformToCustomerReturnsTrueOnSuccess(): void
    {
        $this->dbMock->method('delete')->willReturn(true);
        $this->dbMock->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->is_guest = 1;
        $customer->id = 5;

        $this->assertTrue($customer->transformToCustomer(1, 'validpass'));
    }

    // ── setWsPasswd ──────────────────────────────────────────────────────────────

    public function testSetWsPasswdEncryptsWhenIdIsZero(): void
    {
        $customer = new Customer();
        $customer->id = 0;
        $customer->passwd = 'original';
        $customer->setWsPasswd('newpass');

        $this->assertSame(Tools::encrypt('newpass'), $customer->passwd);
    }

    public function testSetWsPasswdEncryptsWhenPasswordChanged(): void
    {
        $customer = new Customer();
        $customer->id = 5;
        $customer->passwd = 'original';
        $customer->setWsPasswd('different');

        $this->assertSame(Tools::encrypt('different'), $customer->passwd);
    }

    public function testSetWsPasswdDoesNotEncryptWhenPasswordUnchanged(): void
    {
        $customer = new Customer();
        $customer->id = 5;
        $encrypted = Tools::encrypt('mypass');
        $customer->passwd = $encrypted;
        $customer->setWsPasswd($encrypted);

        $this->assertSame($encrypted, $customer->passwd);
    }

    public function testSetWsPasswdAlwaysReturnsTrue(): void
    {
        $customer = new Customer();
        $customer->id = 0;
        $this->assertTrue($customer->setWsPasswd('anypass'));
    }

    // ── logout / mylogout ─────────────────────────────────────────────────────────

    public function testLogoutSetsLoggedToZero(): void
    {
        $customer = new Customer();
        $customer->logged = 1;
        $customer->logout();
        $this->assertSame(0, $customer->logged);
    }

    public function testMylogoutSetsLoggedToZero(): void
    {
        $customer = new Customer();
        $customer->logged = 1;
        $customer->mylogout();
        $this->assertSame(0, $customer->logged);
    }

    // ── searchByName ─────────────────────────────────────────────────────────────

    public function testSearchByNameReturnsResults(): void
    {
        $rows = [['id_customer' => 1, 'firstname' => 'John']];
        $this->dbMock->method('executeS')->willReturn($rows);

        $this->assertSame($rows, Customer::searchByName('john'));
    }

    public function testSearchByNameAppliesLimitClause(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->stringContains('LIMIT'))
            ->willReturn([]);

        $result = Customer::searchByName('john', 5);
        $this->assertSame([], $result);
    }

    public function testSearchByNameAppliesSkipDeletedFilter(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('executeS')
            ->with($this->logicalAnd(
                $this->stringContains('deleted'),
                $this->stringContains('= 0')
            ))
            ->willReturn([]);

        $result = Customer::searchByName('john', null, true);
        $this->assertSame([], $result);
    }

    // ── customerIdExistsStatic ───────────────────────────────────────────────────

    public function testCustomerIdExistsStaticReturnsTrueWhenFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('5');
        $this->assertSame(5, Customer::customerIdExistsStatic(5));
    }

    public function testCustomerIdExistsStaticReturnsFalseWhenNotFound(): void
    {
        $this->dbMock->method('getValue')->willReturn('0');
        $this->assertSame(0, Customer::customerIdExistsStatic(99));
    }

    public function testCustomerIdExistsStaticCachesResult(): void
    {
        $this->dbMock->expects($this->once())->method('getValue')->willReturn('5');
        Customer::customerIdExistsStatic(5);
        $result = Customer::customerIdExistsStatic(5);
        $this->assertSame(5, $result);
    }

    // ── getByEmail ───────────────────────────────────────────────────────────────

    public function testGetByEmailReturnsFalseWhenNoRowFound(): void
    {
        $this->dbMock->method('getRow')->willReturn(false);
        $customer = new Customer();
        $this->assertFalse($customer->getByEmail('user@example.com'));
    }

    public function testGetByEmailPopulatesPropertiesWhenFound(): void
    {
        $this->dbMock->method('getRow')->willReturn([
            'id_customer' => 7, 'email' => 'user@example.com',
            'firstname' => 'John', 'lastname' => 'Doe',
        ]);
        $customer = new Customer();
        $result = $customer->getByEmail('user@example.com');
        $this->assertSame(7, $result->id);
        $this->assertSame('John', $result->firstname);
    }

    public function testGetByEmailChecksPasswordWhenProvided(): void
    {
        $this->dbMock
            ->expects($this->once())
            ->method('getRow')
            ->with($this->stringContains('passwd'))
            ->willReturn(false);

        $customer = new Customer();
        $result = $customer->getByEmail('user@example.com', 'validpass');
        $this->assertFalse($result);
    }

    // ── delete ───────────────────────────────────────────────────────────────────

    public function testDeleteExecutesCleanupQueriesAndReturnsTrue(): void
    {
        // Customer::delete() calls Db::executes() — PHP method names are case-insensitive,
        // so executes() resolves to the same dispatch slot as executeS(). Mock executeS.
        $this->dbMock->method('executeS')->willReturn([]);
        $this->dbMock->method('execute')->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;

        $this->assertTrue($customer->delete());
    }

    public function testDeleteCallsAtLeastFourExecuteCalls(): void
    {
        $this->dbMock->method('executeS')->willReturn([]);
        $this->dbMock->expects($this->atLeast(4))->method('execute')->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $customer->delete();
        $this->assertSame(5, $customer->id);
    }

    // ── getLastCart ──────────────────────────────────────────────────────────────

    public function testGetLastCartReturnsFalseWhenNoCartsExist(): void
    {
        $customer = new Customer();
        $customer->id = 5;
        $this->assertFalse($customer->getLastCart());
    }

    // ── getLastEmails / getLastConnections ────────────────────────────────────────

    public function testGetLastEmailsReturnsEmptyArrayWhenNoId(): void
    {
        $customer = new Customer();
        $customer->id = null;
        $this->assertSame([], $customer->getLastEmails());
    }

    public function testGetLastEmailsQueriesDbAndReturnsRows(): void
    {
        $rows = [['subject' => 'Welcome', 'language' => 'English']];
        $this->dbMock->method('executeS')->willReturn($rows);

        $customer = new Customer();
        $customer->id = 5;
        $customer->email = 'user@example.com';
        $this->assertSame($rows, $customer->getLastEmails());
    }

    public function testGetLastConnectionsReturnsEmptyArrayWhenNoId(): void
    {
        $customer = new Customer();
        $customer->id = null;
        $this->assertSame([], $customer->getLastConnections());
    }

    // ── getOutstanding ────────────────────────────────────────────────────────────

    public function testGetOutstandingReturnsZeroWhenNoData(): void
    {
        $this->dbMock->method('getValue')->willReturn(false);

        $customer = new Customer();
        $customer->id = 5;
        $this->assertEqualsWithDelta(0.0, $customer->getOutstanding(), 0.001);
    }

    public function testGetOutstandingReturnsDifferenceOfPaidAndRest(): void
    {
        $this->dbMock->method('getValue')->willReturn('500.00', '200.00');

        $customer = new Customer();
        $customer->id = 5;
        $this->assertEqualsWithDelta(300.0, $customer->getOutstanding(), 0.001);
    }

    // ── getWsGroups / setWsGroups ─────────────────────────────────────────────────

    public function testGetWsGroupsReturnsGroupsFromDb(): void
    {
        $rows = [['id' => 3], ['id' => 5]];
        $this->dbMock->method('executeS')->willReturn($rows);

        $customer = new Customer();
        $customer->id = 5;
        $this->assertSame($rows, $customer->getWsGroups());
    }

    public function testSetWsGroupsCleansAndAddsGroupsFromResult(): void
    {
        $this->dbMock->expects($this->once())->method('delete')->willReturn(true);
        $this->dbMock->expects($this->exactly(2))->method('insert')->willReturn(true);

        $customer = new Customer();
        $customer->id = 5;
        $result = $customer->setWsGroups([['id' => 3], ['id' => 5]]);
        $this->assertTrue($result);
    }
}
