# Server Setup

This project has three Docker environments:

- Local development: `docker-compose.yml`
- Production server: `docker-compose.prod.yml`
- Testing server: `docker-compose.testing.yml`

The testing server is intended for a public subdomain through a Cloudflare Tunnel, for example `https://machine.example.com`. It uses its own Docker Compose project, Cloudflare connector container, frontend container, backend containers, and volumes, so testing data stays separate from production data.

## Requirements

Install these on the server:

- Git
- Docker Engine
- Docker Compose v2
- A Cloudflare account with Zero Trust / Tunnels enabled

The testing Docker stack includes its own `cloudflared` connector container. You do not need to install `cloudflared` directly on the server.

## Cloudflare Tunnel Setup

In Cloudflare Zero Trust:

1. Go to `Networks` -> `Tunnels`.
2. Create a new tunnel for testing.
3. Choose the Docker connector option.
4. Copy the generated tunnel token.
5. Add a public hostname for the testing subdomain.

For the public hostname service, use:

```text
http://edge:80
```

That hostname works because the `cloudflared` container and `edge` container are on the same Docker network. The `edge` container routes frontend requests to the Expo web app and backend/admin/API requests to Laravel.

Keep the compose HTTP port bound to `127.0.0.1`. It is only for direct server debugging. Public traffic should enter through the Cloudflare Tunnel container.

## First Testing Server Setup

Clone the repository on the server:

```bash
git clone <repository-url> machine-error-helper
cd machine-error-helper
```

Create the testing compose env file:

```bash
cp .env.testing-server.example .env.testing-server
```

Create the backend testing env file:

```bash
cp apps/backend/.env.testing-server.example apps/backend/.env.testing-server
```

Edit both files before starting the stack.

In root `.env.testing-server`, set the Cloudflare token:

```env
TESTING_PUBLIC_API_URL=https://machine.example.com/api
CLOUDFLARED_TUNNEL_TOKEN=your-cloudflare-testing-tunnel-token
```

Generate a Laravel app key:

```bash
printf 'base64:%s\n' "$(openssl rand -base64 32)"
```

Put the generated value into `APP_KEY` in `apps/backend/.env.testing-server`.

Start the testing environment:

```bash
chmod +x scripts/testing-up.sh scripts/testing-redeploy.sh
./scripts/testing-up.sh
```

This starts PostgreSQL, Redis, Laravel PHP-FPM, backend nginx, Expo web frontend nginx, edge nginx, workers, scheduler, and the Cloudflare Tunnel connector.

Create the default admin user:

```bash
docker compose --env-file .env.testing-server -f docker-compose.testing.yml exec backend php artisan admin:create-default-user
```

Open:

```text
https://testing.example.com/admin
```

Replace `testing.example.com` with the real testing subdomain.

## Redeploy Testing

After pushing changes to the branch used by the testing server:

```bash
./scripts/testing-redeploy.sh
```

This pulls the latest code, rebuilds images, restarts containers, runs migrations, refreshes Laravel caches, and restarts queues.

Useful commands:

```bash
docker compose --env-file .env.testing-server -f docker-compose.testing.yml ps
docker compose --env-file .env.testing-server -f docker-compose.testing.yml logs -f --tail=200
docker compose --env-file .env.testing-server -f docker-compose.testing.yml logs -f cloudflared
docker compose --env-file .env.testing-server -f docker-compose.testing.yml exec backend php artisan about
```

## Testing Environment Variables

### Root `.env.testing-server`

Used by Docker Compose before containers start.

| Variable | Required | Example | Notes |
| --- | --- | --- | --- |
| `COMPOSE_PROJECT_NAME` | Yes | `machine-error-helper-testing` | Keeps container, network, and volume names separate from production. |
| `TESTING_HTTP_BIND` | Yes | `127.0.0.1` | Local-only debug bind for nginx. Public traffic uses the `cloudflared` container. |
| `TESTING_HTTP_PORT` | Yes | `8092` | Local-only debug port on the server. |
| `TESTING_PUBLIC_API_URL` | Yes | `https://machine.example.com/api` | API URL embedded into the production Expo web build. |
| `CLOUDFLARED_TUNNEL_TOKEN` | Yes | Cloudflare token | Token copied from the Cloudflare Docker connector setup. |
| `POSTGRES_DB` | Yes | `machine_error_helper_testing` | Must match backend `DB_DATABASE`. |
| `POSTGRES_USER` | Yes | `app_testing` | Must match backend `DB_USERNAME`. |
| `POSTGRES_PASSWORD` | Yes | strong password | Must match backend `DB_PASSWORD`. |

### Backend `apps/backend/.env.testing-server`

Used by Laravel, workers, scheduler, queues, and admin/API requests.

| Variable | Required | Example | Notes |
| --- | --- | --- | --- |
| `APP_NAME` | Yes | `Machine Error Helper Testing` | Display name used by Laravel. |
| `APP_ENV` | Yes | `testing-server` | Server testing environment name. Do not use Laravel's PHPUnit `.env.testing` here. |
| `APP_KEY` | Yes | `base64:...` | Generate once and keep stable. Changing it invalidates encrypted data and sessions. |
| `APP_DEBUG` | Yes | `false` | Keep false on a public subdomain. |
| `APP_URL` | Yes | `https://machine.example.com` | Public testing URL used for links, redirects, cookies, and storage URLs. |
| `LOG_LEVEL` | No | `info` | Use `debug` temporarily when investigating issues. |
| `DB_CONNECTION` | Yes | `pgsql` | Keep as PostgreSQL. |
| `DB_HOST` | Yes | `postgres` | Docker service name. |
| `DB_PORT` | Yes | `5432` | Internal Docker port. |
| `DB_DATABASE` | Yes | `machine_error_helper_testing` | Must match root `POSTGRES_DB`. |
| `DB_USERNAME` | Yes | `app_testing` | Must match root `POSTGRES_USER`. |
| `DB_PASSWORD` | Yes | strong password | Must match root `POSTGRES_PASSWORD`. |
| `SESSION_DRIVER` | Yes | `redis` | Uses the Redis service. |
| `SESSION_DOMAIN` | No | `null` | Use `.example.com` only if sharing cookies across subdomains is required. |
| `SESSION_SECURE_COOKIE` | Yes | `true` | Required when public access is HTTPS through the tunnel. |
| `QUEUE_CONNECTION` | Yes | `redis` | Required for Horizon workers. |
| `CACHE_STORE` | Yes | `redis` | Uses the Redis service. |
| `REDIS_HOST` | Yes | `redis` | Docker service name. |
| `REDIS_PORT` | Yes | `6379` | Internal Docker port. |
| `MAIL_MAILER` | Yes | `log` | Use `smtp` only when a real SMTP server is configured. |
| `MAIL_FROM_ADDRESS` | Yes | `testing@example.com` | Sender for app emails. |
| `GEMINI_API_KEY` | Optional | `...` | Required only when AI extraction is enabled. |
| `MANUAL_AI_EXTRACTION_ENABLED` | Yes | `false` | Set true only after configuring `GEMINI_API_KEY`. |
| `MANUAL_AI_MODEL` | No | `gemini-2.5-flash` | Manual extraction model. |
| `SCREENSHOT_AI_MODEL` | No | `gemini-2.5-pro` | Screenshot diagnosis model. |
| `TELESCOPE_ENABLED` | No | `true` | Enabled on the testing server for debugging. |
| `TELESCOPE_ALLOWED_EMAILS` | Yes when Telescope is enabled | `admin@example.com` | Comma-separated admin emails allowed to open `/telescope`. |

### Mobile `apps/mobile/.env.testing-server`

Used when building or running the Expo app against the testing server.

| Variable | Required | Example | Notes |
| --- | --- | --- | --- |
| `EXPO_PUBLIC_API_URL` | Yes | `https://machine.example.com/api` | Public API URL that the mobile/web app calls. For the Docker testing stack, this is set through root `TESTING_PUBLIC_API_URL`. |

For local mobile testing against the server:

```bash
cp apps/mobile/.env.testing-server.example apps/mobile/.env
cd apps/mobile
npm install
npm run web
```

## Production Environment Variables

Production uses `docker-compose.prod.yml` and `apps/backend/.env`.

Start from:

```bash
cp apps/backend/.env.example apps/backend/.env
```

Set these values differently from testing:

| Variable | Required | Production guidance |
| --- | --- | --- |
| `APP_ENV` | Yes | `production` |
| `APP_DEBUG` | Yes | `false` |
| `APP_URL` | Yes | Production domain, for example `https://app.example.com` |
| `APP_KEY` | Yes | Generate once for production and keep it stable. |
| `DB_DATABASE` | Yes | Production database name. |
| `DB_USERNAME` | Yes | Production database user. |
| `DB_PASSWORD` | Yes | Strong production-only password. |
| `SESSION_SECURE_COOKIE` | Yes | `true` when served over HTTPS. |
| `MAIL_*` | Depends | Configure real SMTP if email delivery is needed. |
| `GEMINI_API_KEY` | Depends | Required for enabled AI extraction. |

The root production compose file can also receive `POSTGRES_DB`, `POSTGRES_USER`, and `POSTGRES_PASSWORD` from the shell or a root `.env` file. These must match the Laravel database values in `apps/backend/.env`.

## Local Development Environment Variables

Local development uses:

- `apps/backend/.env`
- `apps/mobile/.env`

Backend local setup usually starts from:

```bash
cp apps/backend/.env.example apps/backend/.env
```

Important local values:

| Variable | Local value |
| --- | --- |
| `APP_ENV` | `local` |
| `APP_DEBUG` | `true` |
| `APP_URL` | `http://localhost:8090` |
| `DB_HOST` | `postgres` |
| `DB_DATABASE` | `machine_error_helper` |
| `DB_USERNAME` | `app` |
| `DB_PASSWORD` | `secret` |
| `REDIS_HOST` | `redis` |
| `MAIL_HOST` | `mailpit` |

Mobile local setup:

```bash
cp apps/mobile/.env.example apps/mobile/.env
```

Use:

```text
EXPO_PUBLIC_API_URL=http://localhost:8090/api
```

For Android emulator development, use:

```text
EXPO_PUBLIC_API_URL=http://10.0.2.2:8090/api
```

## Notes

- Do not commit real `.env` files. Only commit `*.example` files.
- Keep testing and production database passwords different.
- Keep testing and production `APP_KEY` values different.
- Point the public tunnel to the edge router, not directly to PHP-FPM or backend nginx.
- For the Docker Cloudflare connector, the tunnel service URL is `http://edge:80`, not `http://127.0.0.1:8092`.
