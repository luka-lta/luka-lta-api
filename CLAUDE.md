# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
# Start dev stack (nginx, php-fpm, mysql, redis, minio, clickhouse)
just dev

# Stop dev stack
just stop

# Install PHP dependencies
just install   # or: composer install

# Lint (run inside container)
just lint
# Runs both phpmd and phpcs against src/

# Build docker images
just build              # development (default)
just build production   # production

# Run a single composer/PHP command inside the container
docker compose -f docker-compose.development.yml run --rm php-fpm <cmd>
```

## Architecture

**PHP 8.3 / Slim 4 API** — PSR-4 namespace `LukaLtaApi\` → `src/`.

### Request Lifecycle

1. `public/index.php` → `SlimFactory` bootstraps Slim with PHP-DI container
2. `RouteMiddlewareCollector` registers all routes and middleware
3. Routes hit `Action` classes (extend `ApiAction`) → call `Service` → call `Repository`
4. `ApiAction::__invoke` wraps `execute()`: catches `ApiException` → typed error response, catches `Throwable` → 500
5. Responses are built via `ApiResult::from(ResultInterface, $statusCode)->getResponse($response)`

### Directory Map

| Path | Purpose |
|------|---------|
| `src/Api/<Domain>/Action/` | One class per endpoint; extend `ApiAction` |
| `src/Api/<Domain>/Service/` | Business logic for that domain |
| `src/Repository/` | All DB/Redis/S3/ClickHouse access |
| `src/Service/` | Shared services (JWT, pagination, caching, avatar, permissions) |
| `src/Value/` | Immutable value objects; no side effects |
| `src/Exception/` | Domain exceptions; all extend `ApiException` for typed HTTP responses |
| `src/Slim/Middleware/` | `AuthMiddleware`, `ApiKeyPermissionMiddleware`, `CORSMiddleware` |
| `src/App/Factory/` | DI factories for PDO, Redis, ClickHouse, MinIO, Telegram |
| `src/Command/` | Symfony Console commands (e.g., `sessions:cleanup` runs via cron hourly) |
| `data/mysql/` | SQL migration files — applied in filename order on container init |

### Auth

Two auth paths handled by `AuthMiddleware`:
- **JWT** via `Authorization: Bearer <token>` header (issued at `POST /auth`)
- **API key** via `X-API-Key` + `Origin` headers — permissions checked by `ApiKeyPermissionMiddleware`

### Data Stores

- **MySQL** — primary persistence (users, links, clicks, sessions, API keys)
- **Redis** — session caching, link item caching (`LinkItemCachingService`)
- **ClickHouse** — analytics/click stats (accessed via `smi2/phpclickhouse`)
- **MinIO (S3-compatible)** — avatar uploads via `S3Repository`

### WebTracking Domain

`src/Api/WebTracking/` is a sub-system with its own sub-domains: `Site`, `SiteConfig`, `TrackEvent`, `TrackingUser`, `TrackingScript`, `Metric`, `Identify`. Manages web analytics separate from link click tracking.

## Coding Conventions

- **PSR-12** enforced by phpcs; max line length 120 (hard limit 200)
- phpmd rules in `phpmd.xml` — run lint before pushing
- New endpoints: create `Action` → `Service` → use existing `Repository`. Register route in `RouteMiddlewareCollector`.
- Exceptions must extend `ApiException` to get proper HTTP status codes in responses
- Value objects live in `src/Value/` and are constructed via named constructors (`from()`, `fromArray()`, etc.)
