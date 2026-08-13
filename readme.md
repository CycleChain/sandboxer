# Sandboxer

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cyclechain/sandboxer.svg?style=flat-square)](https://packagist.org/packages/cyclechain/sandboxer)
[![Total Downloads](https://img.shields.io/packagist/dt/cyclechain/sandboxer.svg?style=flat-square)](https://packagist.org/packages/cyclechain/sandboxer)
[![License](https://img.shields.io/packagist/l/cyclechain/sandboxer.svg?style=flat-square)](license.md)

A **zero-modification Laravel package** that provides **complete data isolation** for live product demos, SaaS playgrounds, interactive trial sessions, and testing environments—without altering a single line of your existing models, migrations, or database tables.

---

## 🎯 The Problem Sandboxer Solves

When offering live demos or trial sessions for your SaaS or web application, visitors often want to create, edit, or delete data (e.g., creating blog posts, adding products, modifying settings).

Traditionally, developers had to choose between:

1. **Resetting the database periodically**: Interrupts active users and causes data collisions across simultaneous visitors.
2. **Modifying application logic & models**: Polluting codebase with tenant checks, session filters, or custom traits.
3. **Spinning up isolated containers per user**: Expensive, slow, and hard to manage at scale.

### 🚀 The Sandboxer Solution

`Sandboxer` intercepts Eloquent CRUD operations per user session transparently. Any data created, updated, or deleted by a demo visitor exists **only in their isolated sandbox session**.

- **Master Database is 100% untouched**: Production data remains safe and pristine.
- **Zero Model Modifications**: Works out-of-the-box without adding traits or modifying your Eloquent models or migrations.
- **Multi-Visitor Isolation**: Visitor A sees their sandboxed changes; Visitor B sees theirs—neither affects the master database or each other.
- **Automatic Expiration & Cleanup**: Sessions and sandboxed data expire automatically after a configured TTL.

---

## 📦 Installation

Install `Sandboxer` via Composer.

### 1. Production & Live Demo Installation

If you are deploying `Sandboxer` to provide live demo environments, interactive product tours, or sandbox mode in your production or staging SaaS application, install it as a main dependency:

```bash
composer require cyclechain/sandboxer
```

### 2. Development & Testing Installation

If you are using `Sandboxer` strictly for local development isolation, testing, or preview environments, install it as a dev dependency:

```bash
composer require cyclechain/sandboxer --dev
```

---

## ⚙️ Setup & Migration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=sandboxer.config
```

Run the package database migrations (creates `sandbox_sessions` and `sandbox_storage` tables):

```bash
php artisan migrate
```

---

## 🚀 Quick Start

Enable sandbox mode in your `.env` file:

```env
SANDBOXER_ENABLED=true
SANDBOXER_TTL=3600
SANDBOXER_DEMO_EMAIL=admin@yourdomain.com
SANDBOXER_DEMO_PASSWORD=admin
```

That's it! Sandboxer automatically intercepts database operations whenever sandbox mode is activated.

---

## 🔍 Automatic Sandbox Activation

`Sandboxer` can automatically detect and activate sandbox mode for incoming HTTP requests based on:

1. **Subdomains**: `demo.yourdomain.com`, `sandbox.yourdomain.com`, `try.yourdomain.com`
2. **URL Paths**: `/demo`, `/sandbox`, `/try`
3. **Query Parameters**: `?sandbox=1`, `?demo=true`
4. **Active Session Cookie**: `sandbox_session` cookie

Configure auto-detection in `config/sandboxer.php`:

```php
'auto_detection' => [
    'domains' => 'demo.*.com,sandbox.*.com,try.*.com',
    'paths' => '/demo,/sandbox,/try',
    'parameters' => ['sandbox' => '1', 'demo' => 'true'],
],
```

---

## 📖 Usage

### Sandbox Authentication Integration

For applications requiring demo user login, update your login controller (or custom auth handler) using `SandboxAuthHelper`:

```php
use Cyclechain\Sandboxer\Helpers\SandboxAuthHelper;
use Illuminate\Http\Request;

public function login(Request $request)
{
    // Handle demo/sandbox login credentials transparently
    $sandboxResponse = SandboxAuthHelper::handleSandboxLogin($request, '/dashboard');
    if ($sandboxResponse) {
        return $sandboxResponse;
    }

    // Normal application login flow
    return parent::login($request);
}
```

### Manual Sandbox Control

Use the `Sandboxer` Facade to check or control sandbox state manually:

```php
use Cyclechain\Sandboxer\Facades\Sandboxer;

// Check if sandbox mode is active for current request
if (Sandboxer::isActive()) {
    // Current request is sandboxed
}

// Get the current sandbox session UUID
$sandboxId = Sandboxer::currentId();

// Manually destroy current sandbox session and clear storage
app(\Cyclechain\Sandboxer\SandboxManager::class)->destroy();
```

---

## 🧹 Session Expiration & Automatic Cleanup

Sandbox sessions expire automatically based on the `SANDBOXER_TTL` setting (default: 3600 seconds / 1 hour).

To clean up expired sessions, schedule the `SandboxCleanupJob` in your `app/Console/Kernel.php` or `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;
use Cyclechain\Sandboxer\Jobs\SandboxCleanupJob;

Schedule::job(new SandboxCleanupJob())->hourly();
```

Or dispatch manually:

```bash
php artisan queue:work
```

---

## 🏗️ How It Works (Architecture)

```
Incoming Request
    ↓
Sandbox Middleware (detects subdomain, path, query, or cookie)
    ↓
ModelEventInterceptor & Pretend Mode (captures creating, updating, deleting without touching DB)
    ↓
StorageManager (stores operations in sandbox_storage table)
    ↓
SandboxScope & Eloquent Builder (merges master DB data with sandboxed CRUD mutations on reads)
    ↓
Response (displays isolated data to the visitor)
```

1. **Write Interception**: When a model is created, updated, or deleted, `ModelEventInterceptor` captures the attributes, places the database connection in pretend mode (preventing actual SQL writes), and persists the mutation into `sandbox_storage`.
2. **Read Interception**: When Eloquent queries execute (`all()`, `where()`, `find()`), `SandboxScope` overlays sandboxed insertions, updates, and deletions onto the retrieved master data.

---

## ⚙️ Configuration Reference

Edit `config/sandboxer.php`:

```php
return [
    'enabled' => env('SANDBOXER_ENABLED', false),
    'ttl' => env('SANDBOXER_TTL', 3600),

    'demo_credentials' => [
        'email' => env('SANDBOXER_DEMO_EMAIL', 'admin@admin.com'),
        'password' => env('SANDBOXER_DEMO_PASSWORD', 'admin'),
    ],

    // Tables excluded from sandboxing (e.g. system/auth tables)
    'excluded_tables' => ['users', 'sessions', 'password_reset_tokens', 'migrations', 'sandbox_sessions', 'sandbox_storage'],

    'cache' => [
        'enabled' => env('SANDBOXER_CACHE_ENABLED', true),
        'prefix' => env('SANDBOXER_CACHE_PREFIX', 'sandbox'),
        'ttl' => env('SANDBOXER_CACHE_TTL', 3600),
    ],

    'auto_detection' => [
        'domains' => env('SANDBOXER_AUTO_DOMAINS', 'demo.*.com,sandbox.*.com,try.*.com'),
        'paths' => env('SANDBOXER_AUTO_PATHS', '/demo,/sandbox,/try'),
        'parameters' => ['sandbox' => '1', 'demo' => 'true'],
    ],

    'cleanup' => [
        'enabled' => env('SANDBOXER_CLEANUP_ENABLED', true),
        'interval' => env('SANDBOXER_CLEANUP_INTERVAL', 3600),
    ],
];
```

---

## 🧪 Testing

Run the PHPUnit test suite:

```bash
composer test
```

Or via Docker:

```bash
docker run --rm -v $(pwd):/app -w /app laravelsail/php84-composer:latest ./vendor/bin/phpunit --bootstrap vendor/autoload.php packages/cyclechain/sandboxer/tests
```

---

## 📄 License

The MIT License (MIT). Please see [License File](license.md) for more information.

---

## 👨‍💻 Credits

Developed and maintained by **[Fatih Mert Doğancan](https://github.com/fatihmert)** & **[CycleChain](https://cyclechain.io)**.
