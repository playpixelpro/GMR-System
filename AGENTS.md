# Repository guidance

- This is a single Laravel 13 / PHP 8.3 application, not a monorepo. HTTP routes and middleware are wired in `routes/web.php`; business logic is split between `app/Http/Controllers`, `app/Services` (including AMR/PMR calculations and exports), and Eloquent models in `app/Models`.
- For workflow or access-control changes, check both route middleware in `routes/web.php` and the role/gate definitions, then follow existing feature tests. Role boundaries and branch scoping are important parts of the app's behavior.
- Tests use PHPUnit and configure SQLite `:memory:` in `phpunit.xml`; run the full suite with `php artisan test --compact`, or focus with `php artisan test --compact tests/Feature/SomeTest.php` / `--filter=testName`.
- After PHP edits, format with `vendor/bin/pint --dirty --format agent`. Frontend scripts are only `npm run dev` and `npm run build` (Vite); Tailwind 4 is integrated through Vite.
- Local setup/seeding needs a configured database and `NFA_ADMIN_EMAIL` plus `NFA_ADMIN_PASSWORD` (at least 16 characters); see `README.md`. Do not run destructive migration-reset commands against a non-test database.
- `opencode.json` enables the local Laravel Boost MCP via `php artisan boost:mcp`; use its Laravel-aware tools when available. Check installed dependency versions before relying on version-specific APIs.
