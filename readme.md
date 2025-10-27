> **⚠️ CAUTION: This project is currently under active testing and is not ready for production use.**

# Sandboxer

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Build Status][ico-travis]][link-travis]
[![StyleCI][ico-styleci]][link-styleci]

A zero-modification Laravel sandbox package that provides complete data isolation for demo environments without touching your existing codebase.

## ✨ Features

- **Zero Modification**: Works without modifying any of your existing models or migrations
- **Complete Isolation**: Each sandbox session is completely isolated from others
- **Auto-Detection**: Automatically detects and activates sandbox mode
- **Performance Optimized**: Built-in caching layer for optimal performance
- **Auto Cleanup**: Automatic cleanup of expired sandbox sessions
- **Plug & Play**: Install and enable - no configuration required

## 📦 Installation

Via Composer

```bash
composer require cyclechain/sandboxer
```

Publish the configuration file (optional):

```bash
php artisan vendor:publish --tag=sandboxer.config
```

Run migrations:

```bash
php artisan migrate
```

## 🚀 Quick Start

Add to your `.env`:

```env
SANDBOXER_ENABLED=true
SANDBOXER_TTL=3600
SANDBOXER_DEMO_EMAIL=admin@yourdomain.com
SANDBOXER_DEMO_PASSWORD=admin
```

Update your `LoginController` to enable sandbox authentication:

```php
use Illuminate\Http\Request;
use Cyclechain\Sandboxer\Helpers\SandboxAuthHelper;

public function login(Request $request)
{
    // Handle sandbox login
    $response = SandboxAuthHelper::handleSandboxLogin($request, $this->redirectPath());
    if ($response) {
        return $response;
    }
    
    return parent::login($request);
}
```

That's it! Your application now supports sandbox mode!

## 📖 Usage

### Sandbox Authentication Integration

To enable sandbox authentication, update your `LoginController`:

```php
use Cyclechain\Sandboxer\Helpers\SandboxAuthHelper;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function login(Request $request)
    {
        // Handle sandbox login if applicable
        $sandboxResponse = SandboxAuthHelper::handleSandboxLogin($request, $this->redirectPath());
        if ($sandboxResponse) {
            return $sandboxResponse;
        }

        // Normal login flow
        return parent::login($request);
    }
}
```

### Enable/Disable Sandbox

Simply set the `SANDBOXER_ENABLED` environment variable:

```env
SANDBOXER_ENABLED=true  # Enable sandbox mode
SANDBOXER_ENABLED=false # Disable sandbox mode
```

### How It Works

1. When a user visits your application, a unique sandbox session is created
2. All database operations are intercepted and stored in the `sandbox_storage` table
3. The original data remains untouched - only sandboxed versions are modified
4. Each sandbox session is completely isolated from others
5. When a session expires, it's automatically cleaned up

### Demo Mode Detection

Sandbox mode can be automatically activated by:

- **Subdomain**: `demo.yourdomain.com`
- **Path**: `/demo`, `/sandbox`, `/try`
- **Query Parameter**: `?sandbox=1`, `?demo=true`

Configure in `config/sandboxer.php`:

```php
'auto_detection' => [
    'domains' => 'demo.*.com,sandbox.*.com',
    'paths' => '/demo,/sandbox,/try',
    'parameters' => ['sandbox' => '1', 'demo' => 'true'],
],
```

### Manual Sandbox Control

```php
use Cyclechain\Sandboxer\Facades\Sandboxer;

// Check if sandbox is active
if (Sandboxer::isActive()) {
    // Sandbox mode is active
}

// Get current sandbox ID
$sandboxId = Sandboxer::currentId();
```

### Cleanup

Expired sandbox sessions are automatically cleaned up. To run cleanup manually:

```bash
php artisan queue:work --queue=default
```

Or create a scheduled task in `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new \Cyclechain\Sandboxer\Jobs\SandboxCleanupJob())
             ->hourly();
}
```

## ⚙️ Configuration

Edit `config/sandboxer.php`:

```php
return [
    'enabled' => env('SANDBOXER_ENABLED', false),
    'ttl' => env('SANDBOXER_TTL', 3600),
    
    'demo_credentials' => [
        'email' => env('SANDBOXER_DEMO_EMAIL', 'admin@admin.com'),
        'password' => env('SANDBOXER_DEMO_PASSWORD', 'admin'),
    ],
    
    'demo_record_ids' => [1, 2, 3],
    'snapshot_tables' => ['users', 'settings'],
    'auto_register' => true,
    
    'cache' => [
        'enabled' => true,
        'prefix' => 'sandbox',
        'ttl' => 3600,
    ],
    
    'cleanup' => [
        'enabled' => true,
        'interval' => 3600,
    ],
];
```

## 🏗️ Architecture

### Zero-Modification Design

This package works by intercepting database operations at the model event level:

1. **Model Events**: Intercepts `creating`, `updating`, `deleting`, and `retrieved` events
2. **Storage**: Stores all operations in `sandbox_storage` table
3. **Query Interception**: Merges sandbox data with master data
4. **Isolation**: Each sandbox session is completely separate

### Data Flow

```
User Request
    ↓
Sandbox Middleware (creates/loads session)
    ↓
Model Event Interceptor (captures operations)
    ↓
Storage Manager (stores in sandbox_storage)
    ↓
Cache Layer (optimizes performance)
    ↓
Response (with sandboxed data)
```

## 📊 Database Schema

The package creates two tables:

### `sandbox_sessions`
Stores sandbox session metadata:
- Session token
- IP address and user agent
- Expiration time
- Initial state snapshot

### `sandbox_storage`
Stores all sandbox operations:
- Table name and record ID
- Operation type (INSERT, UPDATE, DELETE)
- Full record data
- Changed fields (for UPDATE operations)
- Operation sequence

## 🔒 Security

- All sandbox data is isolated from production data
- Each sandbox session has its own UUID
- Expired sessions are automatically cleaned up
- No data leakage between sandbox sessions

## 🧪 Testing

```bash
composer test
```

## 🤝 Contributing

Please see [contributing.md](contributing.md) for details.

## 📝 License

MIT. Please see the [license file](license.md) for more information.

## 👨‍💻 Credits

- [Fatih Mert Doğancan][link-author]
- [All Contributors][link-contributors]

[ico-version]: https://img.shields.io/packagist/v/cyclechain/sandboxer.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/cyclechain/sandboxer.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/cyclechain/sandboxer/master.svg?style=flat-square
[ico-styleci]: https://styleci.io/repos/12345678/shield

[link-packagist]: https://packagist.org/packages/cyclechain/sandboxer
[link-downloads]: https://packagist.org/packages/cyclechain/sandboxer
[link-travis]: https://travis-ci.org/cyclechain/sandboxer
[link-styleci]: https://styleci.io/repos/12345678
[link-author]: https://github.com/cyclechain
[link-contributors]: ../../contributors