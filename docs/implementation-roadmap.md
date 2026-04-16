# Implementation Roadmap

## Phase 0: Project Scaffold

- Create Laravel 13 app.
- Add Docker Compose.
- Configure PostgreSQL, Redis, queues, and storage.
- Add Filament admin.
- Add Prism with Gemini configuration.
- Add test stack and code style tools.

## Phase 1: Manual Machine Data

- Machines CRUD.
- Software versions CRUD.
- Manual upload CRUD.
- File storage and hashing.
- Manual status lifecycle.

Success criteria:

- Admin can create a machine.
- Admin can upload a PDF manual for that machine.
- Manual file is stored and visible in admin.

## Phase 2: Basic Manual Extraction

- Queue job for `pdftotext`.
- Store manual pages and chunks.
- Add admin page to inspect extracted text.
- Add deterministic code candidate extraction.

Success criteria:

- Uploading a PDF creates searchable text chunks.
- Candidate codes are visible with page numbers.

## Phase 3: Admin-Approved Error Catalog

- Error codes and definitions tables.
- Review suggestions table and admin review queue.
- Approve/edit/reject workflow.
- Manual version overlay fields.

Success criteria:

- Admin can publish an error code definition with source manual/page.
- Existing definitions remain versioned when changed by a newer manual.

## Phase 4: Screenshot Diagnosis MVP

- API endpoint for machine list.
- API endpoint for screenshot upload.
- Image preprocessing.
- OCR extraction.
- Regex matching.
- Result endpoint.

Success criteria:

- User can upload screenshot.
- System finds exact approved code if OCR sees it.
- User receives meaning and repair hint.

## Phase 5: Gemini Integration

- Gemini structured extraction from screenshots.
- Gemini structured extraction from manual chunks.
- Admin review of AI candidates.
- Confidence scoring.

Success criteria:

- Gemini improves results for screenshots where OCR fails.
- AI extraction never publishes without admin approval.

## Phase 6: Mobile App

- Create Expo app.
- Machine selection screen.
- Screenshot picker/camera screen.
- Diagnosis result polling.
- Manual code entry fallback.

Success criteria:

- Same user flow works on Android/iOS/web development builds.

## Phase 7: Semantic Search

- Enable `pgvector`.
- Generate manual chunk embeddings.
- Add fallback semantic search.
- Add admin manual search.

Success criteria:

- Admin can search manuals by meaning.
- User receives possible manual matches when exact code lookup fails.

## Phase 8: Production Hardening

- Rate limits.
- Upload validation and antivirus strategy if needed.
- Failed job dashboard.
- AI cost logging.
- Sentry.
- Backups.
- Role permissions.
- Audit logs for admin changes.

## MVP Scope Recommendation

For the first usable MVP, build:

- admin machines
- manual upload
- manual text extraction
- manually approved error-code definitions
- repair hints
- user machine selection
- screenshot upload
- OCR + regex exact match
- manual typed code fallback

Add Gemini and embeddings immediately after this baseline, not before the app has a deterministic path that works.
