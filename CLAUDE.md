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
# Runs: composer install, creates .env, generates key, runs migrations, npm install, npm run build,
# and configures git pre-commit hooks
```

### Development Server
```bash
composer dev
# Starts all development services concurrently:
# - PHP server (php artisan serve)
# - Queue worker (php artisan queue:listen) — default queue
# - Transcription worker (--queue=transcription) — WhisperX transcription jobs
# - Video processing worker (--queue=video-processing) — vertical video conversion jobs
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

Formatting is run automatically via a pre-commit hook (configured by `composer setup`), so there is no need to run formatters manually before committing. To run them on demand:

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

## Queue Architecture

The application uses dedicated queue workers for long-running jobs. Each queue maps to a separate process in the Procfile and is scaled independently in preview deployments.

| Queue | Worker | Timeout | Purpose |
|---|---|---|---|
| `default` | `worker` | 60s | General background jobs |
| `transcription` | `transcription` | 3600s | WhisperX sermon video transcription |
| `video-processing` | `video-processing` | 7200s | Vertical video conversion (ffmpeg) |

When adding a new queue, update all three places:
1. **`Procfile`** — add a new process entry
2. **`.github/workflows/preview-deploy.yml`** — add to `process-scaling`
3. **`composer.json`** `dev` script — add a `queue:listen` entry for local development

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

## Conventions

- **Immutable timestamps**: Always use `immutable_datetime` (or `immutable_date`) casts for datetime model attributes. This ensures all date properties return `CarbonImmutable` instances, preventing accidental mutation of date values.

## PHPDoc Conventions

### Filament Page Record Types

When a Filament resource page (e.g. `ViewRecord`, `EditRecord`) needs typed access to `$this->getRecord()`, add a `@method` annotation to the class docblock rather than using `@var` with a local variable assignment:

```php
// Good: @method annotation on the class
/**
 * @extends ViewRecord<SermonVideo>
 *
 * @method SermonVideo getRecord()
 */
class ViewSermonVideo extends ViewRecord
{
    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->title;
    }
}

// Bad: @var with local variable
public function getTitle(): string|Htmlable
{
    /** @var SermonVideo $record */
    $record = $this->getRecord();
    return $record->title;
}
```

### Filament Page Titles

When customizing the title of a Filament resource page, override `getTitle()` rather than `getHeading()`. `getTitle()` sets the browser tab title and the on-page heading defaults to it, so overriding `getTitle()` updates both. Overriding only `getHeading()` leaves the browser tab with the generic Filament default.

```php
// Good: override getTitle() — sets both browser tab and page heading
public function getTitle(): string|Htmlable
{
    return $this->getRecord()->title;
}

// Bad: override getHeading() — only sets the page heading, not the browser tab
public function getHeading(): string|Htmlable
{
    return $this->getRecord()->title;
}
```

## Code Style

**PHP**: Uses Laravel Pint with default Laravel preset
- 4 spaces for indentation
- PSR-12 compatible

**JavaScript/CSS**: Uses Prettier
- Configuration inherits from project defaults
- Automatically formatted by the pre-commit hook

**EditorConfig**: [.editorconfig](.editorconfig)
- 4 spaces for PHP, 2 spaces for YAML
- UTF-8 encoding, LF line endings
