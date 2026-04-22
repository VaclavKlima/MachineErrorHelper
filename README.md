# Machine Error Helper

Monorepo for an application that identifies machine dashboard error codes from screenshots and explains them using administrator-managed manuals and diagnostic entries.

The repository currently contains the initial Laravel backend/admin scaffold, Docker development stack, Expo mobile scaffold, and planning documentation for the manual ingestion and diagnosis pipeline.

## Product Summary

The application has two major surfaces:

- Administration: Laravel + Tailwind based back office for configuring machines, software/manual versions, PDF manuals, extracted error codes, and diagnostic entries.
- User interface: Expo client for mobile and web where a user selects a machine, uploads a screenshot of the machine dashboard, and receives the likely error meaning plus fix guidance.

The difficult part is not only reading a code from an image. The difficult part is maintaining a reliable, versioned knowledge base from large and inconsistent PDF manuals.

## Recommended Direction

- Backend: Laravel 13, PHP 8.5, PostgreSQL, Redis, queue workers.
- Admin: Filament 5 panel builder on top of Laravel, Tailwind, and Livewire.
- User app: Expo React Native with TypeScript, targeting iOS, Android, and browser web from one codebase.
- AI layer: Prism PHP with Gemini configured through `GEMINI_API_KEY`.
- Database: PostgreSQL by default because Laravel 13 supports semantic/vector search with `pgvector`. MySQL is possible, but weaker for this document-search use case.
- OCR and PDF tooling: Poppler `pdftotext` for text PDFs, Tesseract/OCRmyPDF for scanned PDFs or image-heavy pages, Gemini for structured extraction and visual fallback.

## Monorepo Layout

```text
apps/backend    Laravel 13 API, Filament admin, queues, domain models
apps/mobile     Expo React Native user interface scaffold
docker          PHP-FPM and nginx container configuration
docs            Architecture and implementation notes
```

## Current Scaffold Status

Implemented:

- Docker Compose with PHP 8.5 FPM, nginx, PostgreSQL + pgvector, Redis, workers, scheduler, and Mailpit.
- Laravel 13 backend with Filament 5, Prism, Horizon, Telescope, Sanctum, Media Library, permissions, PDF/OCR helpers, and Sentry package installed.
- Domain migrations and models for machines, manuals, versions, manual chunks, error codes, definitions, diagnostic entries, diagnosis requests, and diagnosis candidates.
- Initial JSON API for machine listing and screenshot diagnosis requests.
- Initial Filament resources generated for the main admin records.
- Expo TypeScript app with machine loading, screenshot upload flow, and dark machinist UI for mobile and web.
- Laravel Boost installed in `apps/backend` for framework-aware AI assistance.

Still placeholder:

- Manual PDF ingestion jobs.
- OCR/Gemini screenshot extraction.
- Admin-polished upload forms and review screens.
- Authentication and role gates.

## Quick Start

Build and start the full local stack:

```bash
sudo docker compose build backend
sudo docker compose up -d
sudo docker compose exec backend php artisan migrate
sudo docker compose exec backend php artisan make:filament-user
```

Useful local URLs:

- Admin: http://localhost:8090/admin
- API: http://localhost:8090/api/machines
- Frontend web app: http://localhost:8081
- Mailpit: http://localhost:8025

## Manual Import

Manuals can be uploaded from the Filament admin under `Manuals`. After upload, use the row action `Extract codes` to extract PDF text, pages, chunks, and review suggestions.

Local PDFs can also be imported from the root `assets/manuals` folder:

```bash
sudo docker compose exec backend php artisan manuals:import "assets/manuals/error verze 1.72.0 (CZE)-1.pdf" --machine=1 --title="Error codes 1.72.0 CZE" --language=cs
```

The importer currently uses Poppler `pdftotext` and deterministic table-row detection. Extraction output goes into `Review suggestions`; approved error codes are created only when an admin reviews and approves a suggestion.

The user frontend is included in `docker compose up`. To run it directly on the host instead:

```bash
cd apps/mobile
cp .env.example .env
npm install
npm run web
```

Static web build:

```bash
cd apps/mobile
npm run web:export
npm run web:serve
```

The same Expo app can also run on Android and iOS through `npm run android` or `npm run ios`.

For Android emulator development, set `EXPO_PUBLIC_API_URL=http://10.0.2.2:8090/api`.

## Documentation Map

- [Architecture](docs/architecture.md)
- [Technology Decisions](docs/technology-decisions.md)
- [Data Model](docs/data-model.md)
- [Manual Ingestion Pipeline](docs/manual-ingestion-pipeline.md)
- [Screenshot Processing](docs/screenshot-processing.md)
- [API and Mobile Frontend](docs/api-and-mobile.md)
- [Docker Plan](docs/docker-plan.md)
- [Implementation Roadmap](docs/implementation-roadmap.md)
- [Research Notes](docs/research-notes.md)

## Core Principle

AI should speed up extraction and matching, but it should not be the source of truth. The source of truth should be reviewed, versioned records in the database:

- machine definitions
- manual files
- extracted manual sections
- approved error-code definitions
- user diagnosis history

When confidence is low, the product should ask the user or admin to confirm instead of pretending certainty.
