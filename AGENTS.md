# AGENTS.md - QloApps v1.6.1 Development Guide

## Project Overview

QloApps is an open-source hotel reservation system and booking engine built on PHP. It enables users to launch hotel booking websites and manage online reservations.

## Skills
A skill is a set of local instructions to follow stored in a `SKILL.md` file. Add skills here to make them available in this session.

### Available skills
- e2e-test-creation: Autonomous Playwright QA workflow with strict `playwright-cli` usage. (file: /home/sumit/www/html/QloApps-develop/skills/e2e-test-creation/SKILL.md)
- qloapps-module-development: QloApps module development workflow. (file: /home/sumit/www/html/QloApps-develop/skills/qloapps-module-development/SKILL.md)
- playwright-cli: Playwright CLI interaction and selector discovery. (file: /home/sumit/www/html/QloApps-develop/skills/playwright-cli/SKILL.md)

## Technology Stack

- **Language**: PHP 5.6+ to PHP 7.4
- **Database**: MySQL 5.1+ to 5.7
- **Template Engine**: Smarty
- **Architecture**: MVC (Model-View-Controller) based on PrestaShop framework
- **License**: OSL-3.0 (Core), AFL-3.0 (Modules)

## Directory Structure

```
QloApps161/
├── adminhtl/           # Admin panel files
│   ├── tabs/           # Admin controller tabs
│   └── themes/         # Admin themes
├── cache/              # Cache storage
├── classes/            # Core PHP classes (Models)
│   ├── ObjectModel.php # Base model class
│   ├── Context.php     # Application context
│   ├── Tools.php       # Utility functions
│   └── ...
├── config/             # Configuration files
│   ├── config.inc.php  # Main configuration
│   ├── defines.inc.php # Constants definitions
│   └── smarty.*.inc.php # Smarty configurations
├── controllers/        # Controllers
│   ├── admin/          # Admin controllers
│   └── front/          # Frontend controllers
├── css/                # Stylesheets
├── docs/               # Documentation
├── img/                # Images
├── installdev/         # Installation files
├── js/                 # JavaScript files
├── localization/       # Localization files
├── log/                # Log files
├── mails/              # Email templates
├── modules/            # Modules/Addons
├── override/           # Class overrides
├── pdf/                # PDF templates
├── tests/              # Unit tests
├── themes/             # Frontend themes
│   └── hotel-reservation-theme/
├── tools/              # Utility libraries
│   ├── smarty/         # Smarty template engine
│   └── tcpdf/          # PDF library
├── translations/       # Translation files
├── upload/             # Uploaded files
├── webservice/         # API/WebService
├── index.php           # Application entry point
├── init.php            # Initialization
├── header.php          # Header include
└── footer.php          # Footer include
```

## Key Classes

- **ObjectModel**: Base class for all models (`classes/ObjectModel.php`)
- **Context**: Application context singleton (`classes/Context.php`)
- **Tools**: Utility helper class (`classes/Tools.php`)
- **Dispatcher**: URL routing (`classes/Dispatcher.php`)
- **Controller**: Base controller class
- **Module**: Base module class for addons

## Coding Standards

### PHP Code Style

- Follow PSR-2 coding standards
- Use 4 spaces for indentation (no tabs)
- Opening PHP tag: `<?php`
- Class names: PascalCase (e.g., `CustomerModel`)
- Method names: camelCase (e.g., `getCustomerById()`)
- Constants: UPPER_SNAKE_CASE (e.g., `TYPE_INT`)
- Add license header at the top of PHP files

### File Naming

- Class files: Match class name (e.g., `Customer.php` for `Customer` class)
- Controllers: `{Name}Controller.php`
- Module files: `{modulename}.php`

### Database

- Table prefix: Configurable (default `ps_`)
- Use ObjectModel classes for database operations
- Use `Db::getInstance()` for direct queries when needed

## Module Development

Modules are located in `/modules/`. Each module follows this structure:

```
modules/{modulename}/
├── {modulename}.php    # Main module class
├── views/
│   ├── templates/      # Smarty templates
│   ├── css/            # Module CSS
│   └── js/             # Module JavaScript
├── classes/            # Module-specific classes
├── translations/       # Module translations
└── logo.png            # Module icon
```

### Creating a Module

1. Create directory in `/modules/`
2. Create main PHP file extending `Module` class
3. Implement required methods: `install()`, `uninstall()`
4. Register hooks for integration
5. Create templates in `views/templates/`

## Hooks System

QloApps uses a hook system for extensibility:

```php
// Register a hook
$this->registerHook('displayHeader');

// Implement hook method
public function hookDisplayHeader($params)
{
    // Your code
}

// Call hooks in templates
{hook h='displayHeader'}
```

## Template Development

- Smarty templates use `.tpl` extension
- Main theme: `themes/hotel-reservation-theme/`
- Compile/cache directories: `cache/smarty/`

### Smarty Syntax

```smarty
{$variable}                    <!-- Variable output -->
{l s='String to translate'}    <!-- Translatable string -->
{if condition}...{/if}         <!-- Conditionals -->
{foreach $items as $item}...{/foreach} <!-- Loops -->
{include file='path/to/file.tpl'}     <!-- Include -->
{hook h='hookName'}            <!-- Hook call -->
```

## Running Tests

```bash
cd tests
composer install
../vendor/bin/phpunit
```

## Debugging

- Enable debug mode in `config/config.inc.php`: `_PS_MODE_DEV_ = true`
- Check logs in `/log/` directory
- Use `Tools::d()` and `Tools::p()` for debugging

## Configuration

Key configuration files:

- `config/config.inc.php`: Main configuration
- `config/defines.inc.php`: Path and constant definitions
- `config/settings.inc.php`: Database credentials (auto-generated)

## Common Commands

### Clear Cache

```bash
rm -rf cache/smarty/compile/*
rm -rf cache/smarty/cache/*
```

### Install Dependencies

```bash
composer install
```

## Development Workflow

1. Create feature branch from main
2. Make changes following coding standards
3. Test changes thoroughly
4. Run tests if applicable
5. Submit pull request with clear description

## Version Information

- Current Version: 1.6.1
- Repository: https://github.com/webkul/hotelcommerce

## Support

- Documentation: https://qloapps.com/qlo-reservation-system
- Forum: https://forums.qloapps.com/
- Email: support@qloapps.com
