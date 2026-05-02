<?php
/**
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License version 3.0
* that is bundled with this package in the file LICENSE.md
* It is also available through the world-wide-web at this URL:
* https://opensource.org/license/osl-3-0-php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to support@qloapps.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to a newer
* versions in the future. If you wish to customize this module for your needs
* please refer to https://store.webkul.com/customisation-guidelines for more information.
*
* @author Webkul IN
* @copyright Since 2010 Webkul
* @license https://opensource.org/license/osl-3-0-php Open Software License version 3.0
*/

// ── Exceptions ──────────────────────────────────────────────────────���─────────
// Real PrestaShopException writes log files and renders HTML backtraces.
if (!class_exists('PrestaShopException')) {
    class PrestaShopException extends RuntimeException {}
}
if (!class_exists('PrestaShopDatabaseException')) {
    class PrestaShopDatabaseException extends PrestaShopException {}
}

// ── ObjectModel ───────────────────────────────────────────────────────────────
// Real ObjectModelCore is abstract and requires live DB for add/update/delete.
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

        public static $definition = [];
        public $id = null;
        public static bool $updateResult = true;

        public function __construct($id = null) { $this->id = $id; }
        public function add($autodate = true, $null_values = true) { return true; }
        public function update($null_values = false) { return static::$updateResult; }
        public function delete() { return true; }
        public function save() { return true; }
        public function validateFields($die = true, $error_return = false) { return true; }
        public function toggleStatus() { return true; }
        public function getWebserviceObjectList($sql_join, $sql_filter, $sql_sort, $sql_limit) { return []; }
    }
}

// ── Db ────────────────────────────────────────────────────────────────��───────
// Real DbCore is abstract and requires a live MySQL connection.
// Tests inject a PHPUnit mock via ReflectionProperty into Db::$instance.
if (!class_exists('Db')) {
    class Db
    {
        const INSERT                  = 1;
        const INSERT_IGNORE           = 2;
        const REPLACE                 = 3;
        const ON_DUPLICATE_KEY        = 4;
        const INSERT_ON_DUPLICATE_KEY = 4;

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
        public function escape($str, $htmlOK = false): string { return addslashes((string) $str); }
    }
}

// ── Context ────────────────────────────────────────────────────────────────��──
// Real ContextCore requires mobile_detect lib, cookie, shop, cart objects.
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

// ── Configuration ─────────────────────────────���───────────────────────────────
// Real ConfigurationCore::get() runs SQL. Tests use set()/resetAll() to control values.
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

// ── Tools ────────────────────────────���────────────────────────────────────────
// Real ToolsCore::encrypt() needs _COOKIE_KEY_ secret (from settings.inc.php).
// Real ToolsCore::displayError() needs Context->language->iso_code.
if (!class_exists('Tools')) {
    class Tools
    {
        public static function displayError(string $message = ''): string { return $message; }
        public static function encrypt(string $passwd): string { return md5($passwd); }
        public static function passwdGen(int $length = 8, string $type = 'RANDOM'): string { return str_repeat('a', $length); }
        public static function displayAsDeprecated(): void {}
        public static function cleanNonUnicodeSupport($pattern) { return $pattern; }
        public static function strlen($str, $encoding = 'UTF-8')
        {
            if (null === $str || is_array($str)) { return false; }
            return function_exists('mb_strlen') ? mb_strlen(html_entity_decode($str, ENT_COMPAT, 'UTF-8'), $encoding) : strlen($str);
        }
    }
}

// ── Shop ──────────────────────────────────────────────────────────────────���───
// Real ShopCore extends ObjectModel and uses Db.
if (!class_exists('Shop')) {
    class Shop
    {
        const SHARE_CUSTOMER = 1;
        public static function addSqlRestriction($type = null, $alias = null): string { return ''; }
        public static function addSqlAssociation(string $table, string $alias): string { return ''; }
    }
}

// ── Cache ───────────────────────────��─────────────────────────────────────────
// Real CacheCore is abstract and may write to disk/memory backends.
// Tests need resetAll() for isolation between test cases.
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

// ── Domain stubs ─────────────────────────────────��────────────────────────────
if (!class_exists('Group')) {
    class Group
    {
        private static bool $featureActive = true;
        public static function setFeatureActive(bool $value): void { static::$featureActive = $value; }
        public static function isFeatureActive(): bool { return static::$featureActive; }
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
