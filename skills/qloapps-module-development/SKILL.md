# module-creation.SKILL.md - Module Creation for QloApps v1.6.1

## Overview

Modules are the primary way to extend QloApps functionality. They follow a structured architecture based on the PrestaShop framework and allow developers to add new features, payment methods, shipping options, and more.

## Module Directory Structure

### Basic Module Structure

```
modules/{modulename}/
├── {modulename}.php       # Main module class (required)
├── logo.png               # Module icon (32x32 px)
├── logo.gif               # Module icon for admin list
├── index.php              # Security file
├── composer.json          # Composer configuration (optional)
├── CHANGELOG.txt          # Version history
├── Readme.md              # Documentation
├── views/
│   ├── templates/
│   │   ├── hook/          # Hook display templates
│   │   ├── admin/         # Admin templates
│   │   └── mail/          # Email templates
│   ├── css/
│   │   └── {modulename}.css
│   ├── js/
│   │   └── {modulename}.js
│   └── img/
├── classes/               # Module-specific classes
│   └── {ClassName}.php
├── controllers/
│   ├── admin/             # Admin controllers
│   │   └── Admin{Name}Controller.php
│   └── front/             # Front controllers
│       └── {name}Controller.php
├── translations/
│   └── {lang_code}.php    # Language files
├── upgrade/
│   └── upgrade-{version}.php
└── define.php             # Class includes (optional)
```

### Simple Module Example (blockuserinfo)

```
modules/blockuserinfo/
├── blockuserinfo.php
├── blockuserinfo.tpl
├── blockuserinfo.css
├── nav.tpl
├── nav-xs.tpl
├── logo.png
├── logo.gif
├── index.php
├── CHANGELOG.txt
├── Readme.md
├── composer.json
├── img/
├── translations/
└── upgrade/
```

## Main Module Class

### Basic Module Template

```php
<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyModule extends Module
{
    public function __construct()
    {
        $this->name = 'mymodule';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('My Module');
        $this->description = $this->l('Description of my module.');
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayTop') ||
            !Configuration::updateValue('MYMODULE_SETTING', 1)
        ) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('MYMODULE_SETTING')
        ) {
            return false;
        }
        return true;
    }
}
```

## Module Properties

| Property | Type | Description |
|----------|------|-------------|
| `$name` | string | Module directory name (lowercase, no spaces) |
| `$tab` | string | Admin category for module listing |
| `$version` | string | Module version (e.g., '1.0.0') |
| `$author` | string | Author name |
| `$need_instance` | int | 0 or 1 - create instance on load |
| `$bootstrap` | bool | Use Bootstrap for admin forms |
| `$displayName` | string | Human-readable name |
| `$description` | string | Brief description |
| `$confirmUninstall` | string | Confirmation message on uninstall |
| `$ps_versions_compliancy` | array | Min/max PS version compatibility |

## Module Tabs (Categories)

| Tab | Description |
|-----|-------------|
| `administration` | Admin tools |
| `billing_invoicing` | Billing and invoicing |
| `checkout` | Checkout process |
| `content_management` | CMS features |
| `dashboard` | Dashboard widgets |
| `front_office_features` | Frontend display |
| `i18n_loc` | Internationalization |
| `market_place` | Marketplace features |
| `merchandizing` | Merchandising |
| `migration_tools` | Migration utilities |
| `payments_gateways` | Payment methods |
| `payment_security` | Payment security |
| `pricing_promotion` | Pricing and promotions |
| `quick_bulk_upd` | Quick/bulk updates |
| `search_filter` | Search and filtering |
| `seo` | SEO tools |
| `shipping_logistics` | Shipping |
| `slideshows` | Slideshows |
| `smart_shoppers` | Smart shopping |
| `social_networks` | Social networks |
| `stats` | Statistics |

## Hooks System

### Registering Hooks

```php
public function install()
{
    return parent::install() &&
        $this->registerHook('displayHeader') &&
        $this->registerHook('displayTop') &&
        $this->registerHook('displayFooter');
}
```

### Common Frontend Hooks

| Hook | Description |
|------|-------------|
| `displayHeader` | HTML `<head>` section (CSS/JS) |
| `displayTop` | Top of page (header area) |
| `displayNav` | Navigation area |
| `displayLeftColumn` | Left sidebar |
| `displayRightColumn` | Right sidebar |
| `displayFooter` | Footer area |
| `displayHome` | Homepage content |
| `displayBeforeBodyClosingTag` | Before `</body>` |

### Common Backend Hooks

| Hook | Description |
|------|-------------|
| `displayBackOfficeHeader` | Admin header CSS/JS |
| `displayAdminListBefore` | Before admin lists |
| `actionAdminControllerSetMedia` | Admin controller media |
| `dashboardZoneOne` | Dashboard zone 1 |
| `dashboardZoneTwo` | Dashboard zone 2 |

### Payment Hooks

| Hook | Description |
|------|-------------|
| `displayPayment` | Payment options display |
| `displayPaymentEU` | EU payment options |
| `paymentReturn` | Post-payment display |

### Action Hooks

| Hook | Description |
|------|-------------|
| `actionValidateOrder` | After order validation |
| `actionProductSave` | After product save |
| `actionProductDelete` | After product delete |
| `actionCartSave` | After cart save |
| `actionCustomerAccountAdd` | After customer registration |

### Implementing Hooks

```php
public function hookDisplayHeader()
{
    $this->context->controller->addCSS($this->_path.'views/css/mymodule.css');
    $this->context->controller->addJS($this->_path.'views/js/mymodule.js');
}

public function hookDisplayTop($params)
{
    $this->context->smarty->assign(array(
        'my_variable' => Configuration::get('MYMODULE_SETTING'),
        'module_dir' => $this->_path,
    ));
    return $this->display(__FILE__, 'views/templates/hook/mymodule.tpl');
}

public function hookDisplayFooter($params)
{
    return $this->display(__FILE__, 'mymodule.tpl');
}
```

## Configuration Management

### Saving Configuration

```php
Configuration::updateValue('MYMODULE_SETTING', $value);
Configuration::updateValue('MYMODULE_STRING', 'some text');
```

### Retrieving Configuration

```php
$value = Configuration::get('MYMODULE_SETTING');
$values = Configuration::getMultiple(array('SETTING1', 'SETTING2'));
```

### Deleting Configuration

```php
Configuration::deleteByName('MYMODULE_SETTING');
```

## Admin Configuration Form

### Creating Configuration Page

```php
public function getContent()
{
    $output = '';
    
    if (Tools::isSubmit('submitMyModule')) {
        $config_value = (int)Tools::getValue('MYMODULE_CONFIG');
        Configuration::updateValue('MYMODULE_CONFIG', $config_value);
        $output .= $this->displayConfirmation($this->l('Settings updated'));
    }
    
    return $output . $this->renderForm();
}

protected function renderForm()
{
    $fields_form = array(
        'form' => array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs'
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->l('Enable Feature'),
                    'name' => 'MYMODULE_ENABLED',
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')),
                        array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')),
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->l('API Key'),
                    'name' => 'MYMODULE_API_KEY',
                    'desc' => $this->l('Enter your API key'),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
        ),
    );

    $helper = new HelperForm();
    $helper->show_toolbar = false;
    $helper->table = $this->table;
    $helper->identifier = $this->identifier;
    $helper->submit_action = 'submitMyModule';
    $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
        . '&configure=' . $this->name . '&tab_module=' . $this->tab
        . '&module_name=' . $this->name;
    $helper->token = Tools::getAdminTokenLite('AdminModules');
    $helper->tpl_vars = array(
        'fields_value' => $this->getConfigFieldsValues(),
        'languages' => $this->context->controller->getLanguages(),
        'id_language' => $this->context->language->id,
    );

    return $helper->generateForm(array($fields_form));
}

protected function getConfigFieldsValues()
{
    return array(
        'MYMODULE_ENABLED' => Configuration::get('MYMODULE_ENABLED'),
        'MYMODULE_API_KEY' => Configuration::get('MYMODULE_API_KEY'),
    );
}
```

### Form Field Types

| Type | Description |
|------|-------------|
| `text` | Text input |
| `textarea` | Multi-line text |
| `switch` | On/off toggle |
| `checkbox` | Checkbox group |
| `radio` | Radio buttons |
| `select` | Dropdown |
| `file` | File upload |
| `date` | Date picker |
| `color` | Color picker |
| `html` | HTML content |
| `password` | Password field |
| `group` | Group of fields |

## Template Development

### Smarty Template Example

```smarty
<!-- modules/mymodule/views/templates/hook/mymodule.tpl -->
<div class="mymodule-container">
    <h3>{$module_title}</h3>
    {if $show_content}
        <p>{$module_content}</p>
    {/if}
    {foreach from=$items item=item}
        <div class="item">
            <span>{$item.name}</span>
            <span>{$item.price|string_format:"%.2f"}</span>
        </div>
    {/foreach}
    <a href="{$link->getModuleLink('mymodule', 'action')|escape:'html'}">
        {l s='View More' mod='mymodule'}
    </a>
</div>
```

### Assigning Variables to Templates

```php
$this->context->smarty->assign(array(
    'module_title' => $this->l('My Module'),
    'show_content' => true,
    'module_content' => $content,
    'items' => $itemsArray,
));
```

## Module Classes (ObjectModel)

### Creating Custom Model

```php
<?php
class MyCustomClass extends ObjectModel
{
    public $id;
    public $name;
    public $description;
    public $active = true;
    public $date_add;
    public $date_upd;

    public static $definition = array(
        'table' => 'my_custom_table',
        'primary' => 'id_custom',
        'multilang' => true,
        'fields' => array(
            'name' => array('type' => self::TYPE_STRING, 'validate' => 'isGenericName', 'required' => true, 'size' => 128),
            'description' => array('type' => self::TYPE_HTML, 'validate' => 'isCleanHtml', 'lang' => true),
            'active' => array('type' => self::TYPE_BOOL, 'validate' => 'isBool'),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );
}
```

### Database Table Creation

```php
public function install()
{
    $sql = array();
    
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'my_custom_table` (
        `id_custom` int(11) NOT NULL AUTO_INCREMENT,
        `name` varchar(128) NOT NULL,
        `active` tinyint(1) NOT NULL DEFAULT 1,
        `date_add` datetime NOT NULL,
        `date_upd` datetime NOT NULL,
        PRIMARY KEY (`id_custom`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';
    
    $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'my_custom_table_lang` (
        `id_custom` int(11) NOT NULL,
        `id_lang` int(11) NOT NULL,
        `description` text,
        PRIMARY KEY (`id_custom`, `id_lang`)
    ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }
    
    return parent::install();
}
```

## Front Controllers

### Creating Front Controller

```php
<?php
// modules/mymodule/controllers/front/mypage.php
class MymoduleMypageModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        
        $this->context->smarty->assign(array(
            'custom_var' => 'value',
        ));
        
        $this->setTemplate('module:mymodule/views/templates/front/mypage.tpl');
    }

    public function postProcess()
    {
        if (Tools::isSubmit('submitForm')) {
            // Handle form submission
        }
    }
}
```

### Linking to Front Controller

```php
$link = $this->context->link->getModuleLink('mymodule', 'mypage');
```

```smarty
<a href="{$link->getModuleLink('mymodule', 'mypage')|escape:'html'}">
    {l s='My Page' mod='mymodule'}
</a>
```

## Admin Controllers

### Creating Admin Controller

```php
<?php
// modules/mymodule/controllers/admin/AdminMyModuleController.php
class AdminMyModuleController extends ModuleAdminController
{
    public function __construct()
    {
        $this->table = 'my_custom_table';
        $this->className = 'MyCustomClass';
        $this->lang = true;
        $this->bootstrap = true;
        
        parent::__construct();
        
        $this->fields_list = array(
            'id_custom' => array(
                'title' => 'ID',
                'align' => 'center',
                'width' => 25
            ),
            'name' => array(
                'title' => $this->l('Name'),
            ),
            'active' => array(
                'title' => $this->l('Status'),
                'active' => 'status',
                'type' => 'bool',
            ),
        );
    }

    public function renderForm()
    {
        $this->fields_form = array(
            'legend' => array(
                'title' => $this->l('Item'),
                'icon' => 'icon-cog'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Name'),
                    'name' => 'name',
                    'required' => true,
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Active'),
                    'name' => 'active',
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'active_on', 'value' => 1, 'label' => $this->l('Enabled')),
                        array('id' => 'active_off', 'value' => 0, 'label' => $this->l('Disabled')),
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save'),
            ),
        );
        
        return parent::renderForm();
    }
}
```

### Registering Admin Tab

```php
public function install()
{
    if (!parent::install()) {
        return false;
    }
    
    // Create admin tab
    $tab = new Tab();
    $tab->active = 1;
    $tab->class_name = 'AdminMyModule';
    $tab->name = array();
    foreach (Language::getLanguages(true) as $lang) {
        $tab->name[$lang['id_lang']] = 'My Module';
    }
    $tab->id_parent = (int)Tab::getIdFromClassName('AdminCatalog');
    $tab->module = $this->name;
    
    return $tab->add();
}
```

## Payment Module Development

### Payment Module Structure

```
modules/{paymentname}/
├── {paymentname}.php          # Main payment class (extends PaymentModule)
├── logo.png                    # Module icon (32x32)
├── logo.gif                    # Admin list icon
├── index.php                   # Security file
├── views/
│   ├── templates/
│   │   ├── hook/
│   │   │   ├── payment.tpl         # Payment option on checkout
│   │   │   └── payment_return.tpl  # Order confirmation display
│   │   ├── front/
│   │   │   └── payment_execution.tpl  # Intermediate payment page
│   │   └── mail/                   # Email templates
│   ├── css/
│   └── js/
├── controllers/
│   └── front/
│       ├── payment.php         # Payment processing controller
│       ├── validation.php      # Order validation controller
│       └── callback.php        # Webhook/callback (optional)
└── classes/                    # Helper classes (optional)
    ├── {Payment}Db.php         # Database operations
    ├── {Payment}Helper.php     # API helpers
    └── {Payment}Order.php      # Order model
```

### Payment Module Template

```php
<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class MyPayment extends PaymentModule
{
    public function __construct()
    {
        $this->name = 'mypayment';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Your Name';
        $this->controllers = array('payment', 'validation');
        $this->is_eu_compatible = 1;
        
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        
        $this->bootstrap = true;
        parent::__construct();
        
        $this->displayName = $this->l('My Payment Method');
        $this->description = $this->l('Process payments via My Payment Gateway.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall?');
        
        $this->payment_type = OrderPayment::PAYMENT_TYPE_ONLINE;
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('payment') ||
            !$this->registerHook('displayPaymentEU') ||
            !$this->registerHook('paymentReturn') ||
            !Configuration::updateValue('MYPAYMENT_ENABLED', 1)
        ) {
            return false;
        }
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() ||
            !Configuration::deleteByName('MYPAYMENT_ENABLED') ||
            !Configuration::deleteByName('MYPAYMENT_API_KEY')
        ) {
            return false;
        }
        return true;
    }

    public function hookPayment($params)
    {
        if (!$this->active) {
            return;
        }
        
        if (!$this->checkCurrency($params['cart'])) {
            return;
        }
        
        $this->smarty->assign(array(
            'this_path' => $this->_path,
        ));
        
        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active || !$this->checkCurrency($params['cart'])) {
            return;
        }
        
        return array(
            'cta_text' => $this->l('Pay with My Payment'),
            'logo' => Media::getMediaPath(dirname(__FILE__).'/logo.png'),
            'action' => $this->context->link->getModuleLink($this->name, 'validation', array(), true)
        );
    }

    public function hookPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }
        
        $objOrder = $params['objOrder'];
        $idOrderState = $objOrder->getCurrentState();
        
        $smartyVars = array();
        
        if ($idOrderState == Configuration::get('PS_OS_PAYMENT_ACCEPTED')
            || $idOrderState == Configuration::get('PS_OS_AWAITING_PAYMENT')
        ) {
            if ($objOrder->is_advance_payment) {
                $total = $objOrder->advance_paid_amount;
            } else {
                $total = $objOrder->total_paid;
            }
            
            $smartyVars['status'] = 'ok';
            $smartyVars['total_to_pay'] = Tools::displayPrice($total, $params['currencyObj'], false);
            $smartyVars['id_order'] = $objOrder->id;
            $smartyVars['reference'] = $objOrder->reference;
        } else {
            $smartyVars['status'] = 'failed';
        }
        
        $this->smarty->assign($smartyVars);
        return $this->display(__FILE__, 'views/templates/hook/payment_return.tpl');
    }

    public function checkCurrency($cart)
    {
        $currency_order = new Currency($cart->id_currency);
        $currencies_module = $this->getCurrency($cart->id_currency);
        
        if (is_array($currencies_module)) {
            foreach ($currencies_module as $currency_module) {
                if ($currency_order->id == $currency_module['id_currency']) {
                    return true;
                }
            }
        }
        return false;
    }
}
```

### Payment Validation Controller

```php
<?php
// controllers/front/validation.php
class MyPaymentValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $cart = $this->context->cart;
        
        if ($cart->id_customer == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        
        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'mypayment') {
                $authorized = true;
                break;
            }
        }
        
        if (!$authorized) {
            die($this->module->l('This payment method is not available.', 'validation'));
        }
        
        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        
        $currency = $this->context->currency;
        
        if ($cart->is_advance_payment) {
            $total = $cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
        } else {
            $total = $cart->getOrderTotal(true, Cart::BOTH);
        }
        
        $orderStatus = Configuration::get('PS_OS_PAYMENT_ACCEPTED');
        
        $this->module->validateOrder(
            $cart->id,
            $orderStatus,
            $total,
            $this->module->displayName,
            null,
            array(),
            (int)$currency->id,
            false,
            $customer->secure_key
        );
        
        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart='.$cart->id.
            '&id_module='.$this->module->id.
            '&id_order='.$this->module->currentOrder.
            '&key='.$customer->secure_key
        );
    }
}
```

### Payment Template (payment.tpl)

```smarty
<div class="row">
    <div class="col-xs-12">
        <p class="payment_module">
            <a class="mypayment" href="{$link->getModuleLink('mypayment', 'payment')|escape:'html':'UTF-8'}" 
               title="{l s='Pay with My Payment' mod='mypayment'}">
                {l s='Pay with My Payment' mod='mypayment'}
            </a>
        </p>
    </div>
</div>
```

### Payment Return Template (payment_return.tpl)

```smarty
{if $status == 'ok'}
    <p class="alert alert-success">
        {l s='Your booking has been created successfully!' mod='mypayment'}
    </p>
    <p>
        {l s='Order reference:' mod='mypayment'} <strong>{$reference}</strong><br>
        {l s='Total:' mod='mypayment'} <strong>{$total_to_pay}</strong>
    </p>
{else}
    <p class="warning">
        {l s='We noticed a problem with your order.' mod='mypayment'}
        <a href="{$link->getPageLink('contact', true)|escape:'html'}">
            {l s='Contact support' mod='mypayment'}
        </a>
    </p>
{/if}
```

### Payment Types

| Type | Constant | Description |
|------|----------|-------------|
| Online | `OrderPayment::PAYMENT_TYPE_ONLINE` | Real-time online payment |
| Remote | `OrderPayment::PAYMENT_TYPE_REMOTE_PAYMENT` | Bank transfer, check, etc. |

### Order Status Constants

| Constant | Description |
|----------|-------------|
| `PS_OS_AWAITING_PAYMENT` | Awaiting payment |
| `PS_OS_PAYMENT_ACCEPTED` | Payment accepted |
| `PS_OS_PARTIAL_PAYMENT_ACCEPTED` | Partial/advance payment accepted |
| `PS_OS_ERROR` | Payment error |
| `PS_OS_CANCELED` | Order canceled |

### Advance Payment Support

QloApps supports advance/partial payment for hotel bookings:

```php
if ($cart->is_advance_payment) {
    $total = $cart->getOrderTotal(true, Cart::ADVANCE_PAYMENT);
    $orderStatus = Configuration::get('PS_OS_PARTIAL_PAYMENT_ACCEPTED');
} else {
    $total = $cart->getOrderTotal(true, Cart::BOTH);
    $orderStatus = Configuration::get('PS_OS_PAYMENT_ACCEPTED');
}
```

### Database Tables for Payment Gateway

```php
public function getModuleSql()
{
    return array(
        "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."mypayment_order` (
            `id_mypayment_order` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
            `order_reference` varchar(20) NOT NULL,
            `id_cart` int(10) UNSIGNED NOT NULL,
            `id_customer` int(10) UNSIGNED NOT NULL,
            `transaction_id` varchar(100) NOT NULL,
            `payment_status` varchar(20) NOT NULL,
            `amount` decimal(10,2) NOT NULL,
            `currency` varchar(5) NOT NULL,
            `response` text,
            `date_add` datetime NOT NULL,
            PRIMARY KEY (`id_mypayment_order`)
        ) ENGINE="._MYSQL_ENGINE_." DEFAULT CHARSET=utf8;"
    );
}
```

## Module Upgrade

### Upgrade Script Structure

```php
<?php
// modules/mymodule/upgrade/upgrade-1.1.0.php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $sql = 'ALTER TABLE `' . _DB_PREFIX_ . 'my_custom_table` 
            ADD COLUMN `new_field` varchar(255) DEFAULT NULL';
    
    if (!Db::getInstance()->execute($sql)) {
        return false;
    }
    
    // Update configuration
    Configuration::updateValue('MYMODULE_NEW_SETTING', 'default_value');
    
    // Register new hooks
    return $module->registerHook('displayFooter');
}
```

## Translations

### Using Translations in PHP

```php
$this->l('String to translate');
$this->l('String with %s', sprintf($variable));
```

### Using Translations in Templates

```smarty
{l s='String to translate' mod='mymodule'}
{l s='String with %s' sprintf=$variable mod='mymodule'}
```

## Security Best Practices

### Always Check Context

```php
public function hookDisplayTop($params)
{
    // Check if module is active
    if (!$this->active) {
        return;
    }
    
    // Check customer is logged in
    if (!$this->context->customer->isLogged()) {
        return;
    }
    
    // Your code
}
```

### Escape Output

```smarty
{$variable|escape:'html'}
{$url|escape:'html':'UTF-8'}
```

### Validate Input

```php
$id = (int)Tools::getValue('id');
$name = pSQL(Tools::getValue('name'));
$email = Validate::isEmail(Tools::getValue('email'));
```

## Common Context Properties

| Property | Description |
|----------|-------------|
| `$this->context->cart` | Current cart object |
| `$this->context->customer` | Current customer |
| `$this->context->cookie` | Cookie object |
| `$this->context->language` | Current language |
| `$this->context->currency` | Current currency |
| `$this->context->shop` | Current shop |
| `$this->context->controller` | Current controller |
| `$this->context->smarty` | Smarty instance |
| `$this->context->link` | Link helper |

## Useful Tools Methods

```php
Tools::getValue('param', $default);       // Get request param
Tools::isSubmit('submit');                 // Check form submit
Tools::displayPrice($price);               // Format price
Tools::redirect($url);                     // Redirect
Tools::getToken($gc);                      // Get CSRF token
Tools::encrypt($string);                   // Encrypt string
Tools::jsonEncode($data);                  // JSON encode
Tools::jsonDecode($json);                  // JSON decode
```

## Database Operations

```php
// Execute single query
Db::getInstance()->execute($sql);

// Get single value
$value = Db::getInstance()->getValue($sql);

// Get single row
$row = Db::getInstance()->getRow($sql);

// Get multiple rows
$rows = Db::getInstance()->executeS($sql);

// Insert
Db::getInstance()->insert('table_name', array(
    'field1' => pSQL($value1),
    'field2' => (int)$value2,
));

// Update
Db::getInstance()->update('table_name', array(
    'field1' => pSQL($value1),
), 'id = ' . (int)$id);

// Delete
Db::getInstance()->delete('table_name', 'id = ' . (int)$id);
```

## Debugging

```php
// Dump and die
Tools::d($variable);
Tools::dieObject($object);

// PrestaShop debug mode
// Set in config/config.inc.php
define('_PS_MODE_DEV_', true);
```

