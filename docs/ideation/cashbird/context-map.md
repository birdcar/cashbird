# Context Map: cashbird

**Phase**: 1
**Scout Confidence**: 72/100
**Verdict**: GO

## Dimensions

| Dimension | Score | Notes |
|---|---|---|
| Scope clarity | 18/20 | All new files listed explicitly in spec. Modified files are stock Laravel files that will exist post-bootstrap. Only ambiguity is whether `birdcar/authkit-laravel` is on Packagist or a private repo — affects `composer require` invocation. |
| Pattern familiarity | 12/20 | No existing codebase to read patterns from. Patterns will be established by this phase. Laravel 13 conventions are well-known; `birdcar/authkit-laravel` internals (trait names, middleware key, Blade directives) are spec-provided but not verifiable pre-install. |
| Dependency awareness | 16/20 | Greenfield — nothing consumes the new files yet. All file dependencies within this phase are self-contained and spec-enumerated. Blast radius is internal to Phase 1. |
| Edge case coverage | 12/20 | Spec covers the key failure modes table (WorkOS unreachable, invalid callback state, Postgres refused, Redis down). Missing: what happens if `birdcar/authkit-laravel` package is not publicly available on Packagist; what `workos:install` actually publishes vs. what the spec assumes. |
| Test strategy | 14/20 | Two test files identified with key test cases enumerated in spec. Filter commands given. SQLite in-memory is Laravel's default test DB — builder must configure tests to use pgsql or RefreshDatabase with SQLite for unit tests while keeping pgsql for feature tests. No existing phpunit.xml to read yet. |

## Key Patterns

No "Pattern to follow" files exist yet — this is a greenfield project. Patterns will be established from:

- Laravel 13 defaults for project structure, routing, model conventions
- `birdcar/authkit-laravel` package conventions — trait names (`HasWorkOSId`, `HasWorkOSPermissions`), middleware key (`workos.auth`), Blade directives (`@workosAuth` / `@endWorkosAuth`), artisan commands (`workos:install`), service provider (`Birdcar\AuthKit\WorkOSServiceProvider`)
- Livewire 3 component conventions (class in `app/Livewire/`, view in `resources/views/livewire/`)
- Tailwind CSS 4 with `@tailwindcss/vite` plugin (not PostCSS plugin — this is the v4 approach)

## Dependencies

No external consumers — this is a greenfield phase. All files created here will be consumed by Phase 2+.

Internal phase dependency order:
1. `composer create-project` → produces `composer.json`, `app/Models/User.php`, `config/`, `routes/web.php`
2. `composer require birdcar/authkit-laravel livewire/livewire` → modifies `composer.json`, enables trait imports
3. `php artisan workos:install` → publishes `config/workos.php`, migrations
4. Config modifications → `config/database.php`, `config/queue.php`, `config/cache.php`, `config/broadcasting.php`
5. Layout files → `resources/views/components/layouts/app.blade.php`, sidebar, dashboard view
6. Livewire component → `app/Livewire/Layout/AppShell.php`
7. Docker files → `docker/Dockerfile`, `docker/docker-compose.yml`, `docker/nginx.conf`, `docker/.env.production`
8. Test files → `tests/Feature/AuthenticationTest.php`, `tests/Feature/DashboardTest.php`

## Conventions

- **Naming**: Laravel 13 conventions — PascalCase classes, snake_case columns, kebab-case view files, dot-notation for nested views
- **Imports**: PSR-4 autoloading; `app/` maps to `App\`; `Birdcar\AuthKit\` namespace for authkit-laravel
- **Error handling**: Laravel default exception handling; authkit-laravel handles WorkOS callback errors with redirect to login + flash
- **Types**: PHP 8.3 — builder should use typed properties, return types, constructor promotion where appropriate
- **Testing**: `tests/Feature/` for HTTP tests; `php artisan test` (Pest or PHPUnit — Laravel 13 ships with Pest by default); filter by class name with `--filter`; use `RefreshDatabase` trait
- **Package manager**: `bun` per user preferences (not npm); `bun add -D tailwindcss @tailwindcss/vite` per spec
- **Assets**: Vite + `@tailwindcss/vite` plugin (Tailwind v4 approach)

## Risks

- **`birdcar/authkit-laravel` availability**: Package may be a private/personal repo not on Packagist. Builder must verify or add VCS repository.
- **Tailwind v4 config approach**: Spec lists `tailwind.config.js` as Modified File, but Tailwind CSS 4 uses CSS-first configuration. Builder should use v4 approach and skip JS config unless specifically needed.
- **Test database configuration**: Laravel defaults tests to SQLite in-memory. Spec asserts PostgreSQL connection as test case — needs careful handling.
- **`workos:install` artisan command scope**: Actual command behavior depends on package implementation. May need fallback to manual `vendor:publish`.
- **`SESSION_DRIVER=database` with Postgres**: Requires `php artisan session:table` and migration before session driver works.
