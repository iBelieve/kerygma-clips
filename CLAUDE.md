# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a Laravel 12 application with Filament (admin panel) integration. The project uses:
- PHP 8.2+
- Laravel Framework 12.x
- Filament 4.5+ for admin panel UI
- SQLite database (default)
- Pest for testing
- Vite with Tailwind CSS 4 for frontend assets
- Larastan (PHPStan) for static analysis
- Laravel Pint for PHP code styling
- Prettier for JavaScript/CSS formatting

## Development Commands

### Initial Setup
```bash
composer setup
# Runs: composer install, creates .env, generates key, runs migrations, npm install, npm run build
```

### Development Server
```bash
composer dev
# Starts all development services concurrently:
# - PHP server (php artisan serve)
# - Queue worker (php artisan queue:listen)
# - Log viewer (php artisan pail)
# - Vite dev server (npm run dev)
```

Alternatively, run services individually:
```bash
php artisan serve          # Start Laravel server
npm run dev                # Start Vite dev server
php artisan queue:listen   # Start queue worker
php artisan pail           # View logs in real-time
```

### Testing
```bash
composer test              # Run full test suite (clears config first)
php artisan test           # Run tests directly
php artisan test --filter ExampleTest  # Run specific test
```

The project uses **Pest** (not PHPUnit) for testing. Test files use Pest syntax:
```php
test('example', function () {
    expect(true)->toBeTrue();
});
```

### Static Analysis
```bash
composer analyze           # Run Larastan (PHPStan) static analysis
```

The project uses **Larastan** (level 5) with the Livewire plugin for static analysis. Configuration is in `phpstan.neon`.

### Code Formatting
```bash
composer format            # Fix all PHP files with Laravel Pint
composer format:check      # Check PHP formatting without fixing
npm run format             # Format JS/CSS files with Prettier
npm run format:check       # Check JS/CSS formatting without fixing
```

### Database
```bash
php artisan migrate        # Run migrations
php artisan migrate:fresh  # Drop all tables and re-run migrations
php artisan db:seed        # Run seeders
php artisan migrate:fresh --seed  # Fresh migrations + seed
```

### Asset Building
```bash
npm run build              # Build for production
npm run dev                # Development with hot reload
```

## Architecture

### Filament Admin Panel

The application uses **Filament** as its primary admin panel framework, configured at the root path (`/`).

**Panel Configuration**: [app/Providers/Filament/AppPanelProvider.php](app/Providers/Filament/AppPanelProvider.php)
- Default panel ID: `app`
- Path: `/` (root)
- Authentication: Login required with session management
- Primary color: Amber
- Auto-discovers Resources, Pages, and Widgets in `app/Filament/` directories

**Filament Directory Structure**:
```
app/Filament/
├── Resources/     # CRUD interfaces for Eloquent models
├── Pages/         # Custom admin pages
└── Widgets/       # Dashboard widgets
```

When creating Filament resources, pages, or other components, always use Filament's artisan commands first, then modify the generated files as needed. This ensures we follow Filament's latest conventions and best practices.

```bash
php artisan make:filament-resource ModelName          # Create a resource
php artisan make:filament-resource ModelName --generate  # Create with auto-generated form/table
php artisan make:filament-page PageName               # Create a custom page
php artisan make:filament-widget WidgetName           # Create a widget
```

### Application Structure

**Models**: [app/Models/](app/Models/)
- Standard Eloquent models
- User model included by default

**Controllers**: [app/Http/Controllers/](app/Http/Controllers/)
- Standard Laravel controllers (though most admin logic lives in Filament)

**Service Providers**: [app/Providers/](app/Providers/)
- [AppServiceProvider.php](app/Providers/AppServiceProvider.php) - Application services
- [Filament/AppPanelProvider.php](app/Providers/Filament/AppPanelProvider.php) - Filament panel config

**Routes**: [routes/](routes/)
- [web.php](routes/web.php) - Web routes (minimal, Filament handles admin routes)
- [console.php](routes/console.php) - Artisan console routes

**Frontend Assets**: [resources/](resources/)
```
resources/
├── css/
│   └── app.css      # Main Tailwind CSS entry point
├── js/
│   └── app.js       # Main JavaScript entry point
└── views/           # Blade templates (if needed beyond Filament)
```

### Database

- Default: SQLite (`storage/app/database.sqlite`)
- Migrations: [database/migrations/](database/migrations/)
- Factories: [database/factories/](database/factories/)
- Seeders: [database/seeders/](database/seeders/)

### Testing

Tests use **Pest** framework:
- Feature tests: [tests/Feature/](tests/Feature/)
- Unit tests: [tests/Unit/](tests/Unit/)
- Configuration: [tests/Pest.php](tests/Pest.php)
- Base test case: [tests/TestCase.php](tests/TestCase.php)

Custom Pest expectations can be added in [tests/Pest.php](tests/Pest.php).

### Frontend Build

Vite configuration: [vite.config.js](vite.config.js)
- Entry points: `resources/css/app.css`, `resources/js/app.js`
- Uses Tailwind CSS 4 via Vite plugin
- Dev server on `127.0.0.1`
- Ignores `storage/framework/views/` for performance

## Environment Configuration

Copy `.env.example` to `.env` and configure:
- `APP_NAME`: Application name
- `APP_ENV`: `local`, `production`, etc.
- `APP_KEY`: Generate with `php artisan key:generate`
- `DB_CONNECTION`: Default is `sqlite`
- `QUEUE_CONNECTION`: Default is `database`
- `SESSION_DRIVER`: Default is `database`

## Common Artisan Commands

```bash
php artisan tinker            # Interactive REPL
php artisan route:list        # List all routes
php artisan make:model Model  # Create model
php artisan make:migration create_table  # Create migration
php artisan make:factory ModelFactory    # Create factory
php artisan make:seeder ModelSeeder      # Create seeder
php artisan cache:clear       # Clear application cache
php artisan config:clear      # Clear config cache
php artisan view:clear        # Clear compiled views
```

## Code Style

**PHP**: Uses Laravel Pint with default Laravel preset
- 4 spaces for indentation
- PSR-12 compatible

**JavaScript/CSS**: Uses Prettier
- Configuration inherits from project defaults
- Run `npm run format` before committing frontend changes

**EditorConfig**: [.editorconfig](.editorconfig)
- 4 spaces for PHP, 2 spaces for YAML
- UTF-8 encoding, LF line endings
