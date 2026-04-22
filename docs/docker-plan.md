# Docker Plan

Docker should be the default development and deployment path.

## Services

Recommended local Compose services:

```text
app          PHP 8.5 FPM Laravel app
nginx        web server
postgres     PostgreSQL with pgvector
redis        cache, queue, rate limit
worker       Laravel queue worker
scheduler    Laravel scheduler
frontend     Expo web dev server for the user app
node         Vite/Tailwind build for admin assets
mailpit      local email testing
minio        optional S3-compatible storage
```

For the MVP, `app`, `nginx`, `postgres`, `redis`, and `worker` are enough.

## PHP Extensions

Install:

- `pdo_pgsql`
- `intl`
- `mbstring`
- `zip`
- `gd` or `imagick`
- `exif`
- `bcmath`
- `redis`
- `pcntl`

## System Packages

Install in the worker image:

- `poppler-utils` for `pdftotext`, `pdfinfo`, and page rendering utilities
- `tesseract-ocr`
- language packs for expected manual languages
- `ocrmypdf` optional, useful for scanned manuals
- `imagemagick` optional, useful for image preprocessing and PDF page rendering

The web container can be lighter, but sharing the same image is simpler early in development.

## Environment Variables

```env
APP_NAME="Machine Error Helper"
APP_ENV=local
APP_URL=http://localhost

DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=machine_error_helper
DB_USERNAME=app
DB_PASSWORD=secret

QUEUE_CONNECTION=redis
CACHE_STORE=redis
REDIS_HOST=redis

FILESYSTEM_DISK=local

GEMINI_API_KEY=
AI_DEFAULT_PROVIDER=gemini
```

## Volumes

Use named volumes for:

- PostgreSQL data
- Redis data if persistence is desired
- MinIO data if used

Bind mount the app source during development.

## Production Notes

- Run web and worker as separate containers.
- Store manuals/screenshots in S3-compatible storage.
- Use separate queues for heavy manual ingestion and user screenshot diagnosis.
- Set strict upload size limits per route.
- Add monitoring for failed jobs and AI API latency/cost.
