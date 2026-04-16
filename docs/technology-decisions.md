# Technology Decisions

## Backend

Use Laravel 13 with PHP 8.5.

Reasoning:

- Laravel 13 is current and includes first-party AI and search improvements.
- PHP 8.5 is the newest stable PHP branch and gives the longest support window.
- Laravel has strong queue, filesystem, validation, auth, and admin ecosystem support.

Installed Composer packages:

```bash
composer require prism-php/prism
composer require laravel/sanctum
composer require laravel/horizon
composer require filament/filament:"^5.0"
composer require spatie/laravel-medialibrary
composer require spatie/laravel-permission
composer require spatie/pdf-to-text
composer require smalot/pdfparser
composer require thiagoalessio/tesseract_ocr
composer require intervention/image-laravel
composer require sentry/sentry-laravel
```

Installed dev packages:

```bash
composer require --dev laravel/boost
composer require --dev larastan/larastan
composer require --dev laravel/telescope
composer require --dev barryvdh/laravel-ide-helper
```

Notes:

- `prism-php/prism` should manage Gemini calls, structured output, provider abstraction, and tool-style AI workflows.
- Laravel Boost is installed so AI coding tools can understand the Laravel app, docs, and conventions.
- The default Laravel PHPUnit stack is kept for now. Pest can be added later after resolving version constraints cleanly.
- `spatie/pdf-to-text` uses the Poppler `pdftotext` binary. This is usually better than pure PHP parsing for real manuals.
- `smalot/pdfparser` is useful as a fallback or for simple metadata extraction, but should not be the only PDF path.
- `thiagoalessio/tesseract_ocr` is a PHP wrapper around the Tesseract binary.
- `intervention/image-laravel` is useful for screenshot preprocessing before OCR or AI calls.

## Database

Use PostgreSQL by default.

Reasoning:

- This application is document-heavy.
- Laravel 13 supports PostgreSQL vector search with `pgvector`.
- PostgreSQL gives strong full-text search, JSONB, indexing, and versioned data modeling.

MySQL remains possible if the application only needs exact code lookup. It becomes less attractive once we want semantic search over large manuals and chunks.

## Search

Use a combined strategy:

- exact normalized code lookup for primary result
- PostgreSQL full-text search for manual text
- PostgreSQL `pgvector` for semantic fallback over manual chunks
- optional reranking through Prism/Gemini if needed later

Do not start with Meilisearch, Typesense, or Elasticsearch unless PostgreSQL search is proven insufficient.

## Admin

Use Filament 5.

Reasoning:

- Fast CRUD for machines, manuals, versions, code definitions, hints, and review queues.
- It already uses Tailwind and works naturally with Laravel.
- It avoids spending early effort on custom admin UI.

Admin resources to build first:

- MachineResource
- SoftwareVersionResource
- ManualResource
- ManualImportRunResource
- ErrorCodeResource
- RepairHintResource
- DiagnosisRequestResource

## User Frontend

Use Expo React Native with TypeScript.

Reasoning:

- Same codebase can target iOS, Android, and web.
- The upload/camera flow maps well to mobile.
- It keeps the Laravel app API-first instead of tying user UI to Blade/Inertia.

Recommended frontend packages:

```bash
npx create-expo-app@latest user-app --template
npx expo install expo-image-picker expo-file-system expo-camera
npm install @tanstack/react-query zod
npm install nativewind tailwindcss
npm install zustand
```

## AI

Use Gemini through Prism PHP.

Main AI tasks:

- extract candidate error code strings from screenshots
- extract structured error definitions from manual chunks
- summarize manual text into admin-reviewable suggestions
- generate embeddings for semantic search
- rerank candidate manual chunks when exact lookup fails

AI should return structured data. Do not parse free-form model prose for important business logic.

Example structured screenshot result:

```json
{
  "codes": [
    {
      "value": "E1042",
      "confidence": 0.92,
      "visible_text": "Alarm E1042",
      "reason": "Code appears in red alarm banner"
    }
  ],
  "raw_dashboard_text": "Alarm E1042 Servo overload",
  "needs_user_confirmation": false
}
```

## File Storage

Local disk is fine for development. Production should support S3-compatible storage.

Store:

- original PDFs
- OCR-generated PDFs/text
- manual page images if needed
- uploaded screenshots
- generated thumbnails/crops for admin review

## Queue

Use Redis + Laravel Horizon.

Queue names:

- `manual-ingestion`
- `manual-ai-extraction`
- `manual-embeddings`
- `screenshot-diagnosis`
- `notifications`

Manual ingestion can take minutes for large PDFs, so it must never run in a web request.
