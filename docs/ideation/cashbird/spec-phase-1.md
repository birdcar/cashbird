# Implementation Spec: Cashbird - Phase 1: Foundation & Auth

**Contract**: ./contract.md
**Estimated Effort**: M

## Technical Approach

Bootstrap a Laravel 13 application without a starter kit, configured for PostgreSQL, Redis, and WorkOS AuthKit via the `birdcar/authkit-laravel` package. The app uses Livewire for all interactive UI (matching authkit-laravel's widget pattern). Tailwind CSS 4 for styling.

The deployment target is a Beelink mini-PC running Coolify, so we provide a Dockerfile and docker-compose.yml with Postgres, Redis, and the Laravel app as services. The Coolify deployment uses the docker-compose approach.

No Breeze, Jetstream, or any starter kit. Auth is entirely handled by authkit-laravel (redirects to WorkOS hosted login, callback handling, session management via encrypted cookie).

## Feedback Strategy

**Inner-loop command**: `php artisan test --filter=Foundation`

**Playground**: Dev server (`php artisan serve`) + test suite. Auth flow requires WorkOS credentials but can be smoke-tested with route existence checks and middleware assertions.

**Why this approach**: Foundation phase is mostly config and wiring — test suite validates routes exist, middleware is applied, and models are configured. Dev server confirms the shell renders.

## File Changes

### New Files

| File Path | Purpose |
|-----------|---------|
| `docker/Dockerfile` | Multi-stage PHP 8.3 + Nginx production image |
| `docker/docker-compose.yml` | App + Postgres + Redis service stack for Coolify |
| `docker/nginx.conf` | Nginx config for Laravel |
| `docker/.env.production` | Production env template (no secrets, just structure) |
| `app/Livewire/Layout/AppShell.php` | Main app layout Livewire component |
| `resources/views/components/layouts/app.blade.php` | Base Blade layout with nav, sidebar |
| `resources/views/livewire/dashboard.blade.php` | Dashboard view (placeholder for Phase 2+) |
| `resources/views/livewire/layout/sidebar.blade.php` | Sidebar navigation component |
| `tests/Feature/AuthenticationTest.php` | Auth route and middleware tests |
| `tests/Feature/DashboardTest.php` | Dashboard access and layout tests |

### Modified Files

| File Path | Changes |
|-----------|---------|
| `composer.json` | Add `birdcar/authkit-laravel`, `livewire/livewire` |
| `config/database.php` | Ensure pgsql is default connection |
| `config/queue.php` | Set Redis as default queue driver |
| `config/cache.php` | Set Redis as default cache driver |
| `config/broadcasting.php` | Configure Redis for realtime |
| `config/workos.php` | Published from authkit-laravel; configure features |
| `.env.example` | Add WORKOS_*, DB_CONNECTION=pgsql, QUEUE_CONNECTION=redis vars |
| `routes/web.php` | Add dashboard route, apply auth middleware |
| `app/Models/User.php` | Add HasWorkOSId, HasWorkOSPermissions traits from authkit-laravel |
| `tailwind.config.js` | Configure content paths for Livewire views |

## Implementation Details

### Project Bootstrap

**Overview**: Create Laravel 13 project and install core dependencies.

**Implementation steps**:
1. `composer create-project laravel/laravel cashbird` (if not already created)
2. `composer require birdcar/authkit-laravel livewire/livewire`
3. `php artisan workos:install` — publish config and run authkit-laravel setup
4. Configure `.env` with PostgreSQL connection, Redis, WorkOS credentials
5. `php artisan migrate` — run default Laravel + authkit-laravel migrations
6. Install Tailwind CSS 4 via npm/bun: `bun add -D tailwindcss @tailwindcss/vite`

### Database & Cache Configuration

**Overview**: Configure PostgreSQL as primary database, Redis for cache/queue/broadcasting.

**Implementation steps**:
1. Set `DB_CONNECTION=pgsql` in `.env.example` and `.env`
2. Set `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, `SESSION_DRIVER=database` in env
3. Verify `config/database.php` pgsql section has correct defaults
4. Verify `config/queue.php` redis connection is configured
5. Run `php artisan migrate` to confirm Postgres connectivity

**Feedback loop**:
- **Playground**: Test suite with database assertions
- **Experiment**: Run migrations, verify tables exist, verify Redis is reachable via `php artisan tinker` with `Cache::put('test', 'ok', 60); Cache::get('test')`
- **Check command**: `php artisan test --filter=Foundation`

### Auth Integration

**Overview**: Wire up authkit-laravel for WorkOS AuthKit authentication.

**Implementation steps**:
1. Publish authkit-laravel config: `php artisan vendor:publish --provider="Birdcar\AuthKit\WorkOSServiceProvider"`
2. Configure `config/workos.php`: disable organizations feature, enable webhooks
3. Add `HasWorkOSId` and `HasWorkOSPermissions` traits to `User` model
4. Register authkit-laravel middleware in `bootstrap/app.php`
5. Protect dashboard route with `workos.auth` middleware
6. Verify login redirect → WorkOS hosted UI → callback → session created

**Feedback loop**:
- **Playground**: Create `tests/Feature/AuthenticationTest.php` with route assertion tests
- **Experiment**: Test unauthenticated access redirects to login, authenticated access reaches dashboard, logout clears session
- **Check command**: `php artisan test --filter=Authentication`

### App Shell & Layout

**Overview**: Create the base Livewire layout with sidebar navigation and dashboard placeholder.

**Implementation steps**:
1. Create `resources/views/components/layouts/app.blade.php` as the base layout
2. Include Tailwind CSS, Livewire scripts/styles, sidebar nav
3. Create sidebar with nav links: Dashboard, Accounts (Phase 2), Budget (Phase 4), Debt (Phase 5), Reports (Phase 6)
4. Create `resources/views/livewire/dashboard.blade.php` as landing page
5. Use `@workosAuth` / `@endWorkosAuth` Blade directives for auth-conditional rendering
6. Wire routes: `GET /` → redirect to `/dashboard`, `GET /dashboard` → dashboard view

**Feedback loop**:
- **Playground**: Dev server (`php artisan serve`), navigate to `/dashboard`
- **Experiment**: Verify layout renders with sidebar, nav links are present, Tailwind styles apply, unauthenticated access redirects
- **Check command**: `php artisan test --filter=Dashboard`

### Docker & Coolify Deployment

**Overview**: Create Docker setup for self-hosted deployment via Coolify.

**Implementation steps**:
1. Create `docker/Dockerfile`: multi-stage build with PHP 8.3-FPM, Nginx, Node for asset compilation
2. Create `docker/docker-compose.yml` with services: app, postgres (16), redis (7-alpine)
3. Create `docker/nginx.conf` for Laravel (root at `/var/www/html/public`)
4. Create `docker/.env.production` template with all required vars (no actual secrets)
5. Add volume mounts for Postgres data persistence
6. Configure health checks for all services

## Data Model

### Schema Changes

No new tables in this phase. Uses Laravel's default `users`, `sessions`, `cache`, `jobs` tables plus authkit-laravel's tables (published via its migrations).

Verify User model has:
```php
// app/Models/User.php
use Birdcar\AuthKit\Models\Concerns\HasWorkOSId;
use Birdcar\AuthKit\Models\Concerns\HasWorkOSPermissions;

class User extends Authenticatable
{
    use HasWorkOSId, HasWorkOSPermissions;
}
```

## Testing Requirements

### Feature Tests

| Test File | Coverage |
|-----------|---------|
| `tests/Feature/AuthenticationTest.php` | Auth routes exist, middleware redirects, session handling |
| `tests/Feature/DashboardTest.php` | Dashboard accessible when authenticated, layout renders |

**Key test cases**:
- `GET /dashboard` returns 302 when unauthenticated
- `GET /auth/login` exists and returns redirect to WorkOS
- `GET /auth/callback` route exists
- `GET /auth/logout` route exists
- Dashboard view contains sidebar navigation links
- Database connection is PostgreSQL
- Cache driver is Redis

## Failure Modes

| Component | Failure Mode | Trigger | Impact | Mitigation |
|---|---|---|---|---|
| Auth | WorkOS unreachable | Network issue or WorkOS outage | Users cannot log in | Show clear error page; session-based auth persists for already-authenticated users |
| Auth | Invalid callback state | CSRF or expired auth flow | Login fails silently | Redirect to login with flash error message |
| Database | PostgreSQL connection refused | Docker service not started or env misconfigured | App crashes on boot | Health check in docker-compose; clear error in `.env.example` |
| Redis | Redis connection refused | Redis service down | Queue/cache/broadcasting fail | Laravel falls back to sync queue driver; log warning |

## Validation Commands

```bash
# Run migrations
php artisan migrate --force

# Run tests
php artisan test --filter=Foundation
php artisan test --filter=Authentication
php artisan test --filter=Dashboard

# Type check (if using PHPStan)
# Defer to Phase 2+ — not critical for foundation

# Asset build
bun run build

# Docker build
docker compose -f docker/docker-compose.yml build

# Verify routes
php artisan route:list
```

## Rollout Considerations

- **First deploy**: Coolify needs docker-compose.yml pointed at `docker/docker-compose.yml`
- **Environment**: All secrets configured in Coolify's env management (WORKOS_API_KEY, WORKOS_CLIENT_ID, etc.)
- **Database**: First deploy runs `php artisan migrate --force` automatically via Dockerfile entrypoint
- **DNS**: Configure local DNS or Coolify's built-in domain management

---

_This spec is ready for implementation. Follow the patterns and validate at each step._
