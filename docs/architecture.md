# Architecture

## High-Level Shape

The system should be split into these parts:

- Laravel API and admin application
- PostgreSQL database
- Redis for queue, cache, and rate limiting
- Object/file storage for screenshots and PDF manuals
- Queue workers for PDF ingestion, OCR, embeddings, and AI extraction
- Expo React Native user app
- Optional separate local AI/OCR helper container if system binaries become heavy

```text
Admin Panel
    |
    | upload manuals, configure machines, review extraction
    v
Laravel API/Admin ---- PostgreSQL + pgvector
    |                        |
    | queues                 | canonical machine/manual/error data
    v                        |
Redis Queue Workers ---------
    |
    | pdftotext / OCR / Gemini / embeddings
    v
Manual chunks + extracted error definitions

Expo User App
    |
    | select machine, upload screenshot
    v
Laravel API -> OCR/Gemini extraction -> code match -> explanation + hint
```

## Main Bounded Contexts

### Machine Catalog

Configurable in admin:

- machine name, slug, manufacturer, dashboard type
- supported code patterns, for example `E\d{4}` or `[A-Z]{2}-\d+`
- optional software versions
- manuals connected to the machine

### Manual Knowledge Base

Stores PDF manuals, extracted text, chunks, embeddings, and approved error-code definitions.

Manuals must support ordering and effective versions because newer manuals often contain only changed or new codes. We should treat newer manuals as overlays, not full replacements.

### Diagnosis

Stores user uploads and extraction attempts:

- original screenshot
- OCR text
- AI-extracted candidate codes
- selected machine
- matched definitions
- confidence
- final result shown to user

### Admin Review

Extraction should be reviewable:

- pending extracted codes
- conflicting definitions
- unmatched manual chunks that mention likely codes
- low-confidence screenshot results
- admin hints/tutorials connected to code definitions

## Matching Strategy

Use several layers, from deterministic to AI-assisted:

1. Normalize text and extract possible codes using machine-specific regex patterns.
2. Match exact code against approved definitions for the selected machine.
3. Resolve by manual/software version precedence.
4. If several definitions remain, show candidates with source manual/version.
5. Use semantic search only as fallback, for example when a screenshot says "servo overload" but no exact code is found.

The exact code match should win over semantic search. Semantic search is useful for finding supporting manual text, not for silently overriding code tables.

## Version Overlay Model

Manuals should have a `manual_type` or `coverage_mode`:

- `complete`: manual contains a full code list for its version.
- `delta`: manual contains only changes/new codes compared to previous manual.
- `supplement`: additional troubleshooting information, not authoritative for code replacement.

Definitions should have:

- `effective_from_version`
- `effective_to_version`
- `supersedes_definition_id`
- `source_manual_id`
- `priority`

If the user does not know the software version, the app can show the latest definition by default and expose older definitions as "other manual versions".

## Reliability Rules

- Never delete old definitions when new manuals are imported.
- Keep source links: manual, page number, extracted chunk, extraction method.
- Require admin approval before automatically publishing AI-extracted manual data.
- Log all AI prompts/responses that affect extraction, with redaction for uploaded user images where needed.
- Store confidence separately from truth. A high-confidence extraction is still only a candidate until matched to approved data.
