# Kerygma Clips

A web application for making short sermon clip videos suitable for YouTube Shorts or Facebook Reels.

## Tech Stack

- PHP 8.5
- Laravel 12
- Filament 4 for the app panel
- TailwindCSS 4
- SQLite database
- Pest for testing
- Larastan (PHPStan) for static analysis
- Laravel Pint for PHP code formatting
- Prettier for JavaScript/CSS formatting

## Local Setup

```bash
composer setup
```

This runs `composer install`, creates your `.env` file, generates an app key, runs database migrations, installs npm dependencies, and builds frontend assets.

```bash
composer dev
```

This starts the Laravel server, queue worker, log viewer, and Vite dev server concurrently.

## Useful commands

```bash
composer test     # Running tests
composer analyze  # Static Analysis
composer format   # Format PHP (Pint)
npm run format    # Format JS/CSS (Prettier)
```

## License

This project is licensed under the [GNU Affero General Public License v3.0 or later](https://www.gnu.org/licenses/agpl-3.0.en.html). See the [LICENSE](LICENSE) file for details.
