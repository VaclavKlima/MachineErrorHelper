# Data Model

This is the first-pass model. It is intentionally explicit because the hard part is traceability and versioning.

## Core Tables

### machines

- `id`
- `name`
- `slug`
- `manufacturer`
- `model_number`
- `description`
- `dashboard_notes`
- `is_active`
- `created_at`
- `updated_at`

### machine_code_patterns

Machine-specific regex patterns for extracting possible error codes.

- `id`
- `machine_id`
- `name`
- `regex`
- `normalization_rule`
- `priority`
- `is_active`

### software_versions

- `id`
- `machine_id`
- `version`
- `released_at`
- `sort_order`
- `notes`

### manuals

- `id`
- `title`
- `machine_id`
- `software_version_id` nullable
- `coverage_mode` enum: `complete`, `delta`, `supplement`
- `language`
- `file_path`
- `file_hash`
- `page_count`
- `published_at`
- `source_notes`
- `status` enum: `uploaded`, `processing`, `review_required`, `published`, `failed`

### manual_import_runs

Each upload/processing attempt gets a run so we can debug and rerun safely.

- `id`
- `manual_id`
- `status`
- `started_at`
- `finished_at`
- `error_message`
- `extractor_versions` JSON
- `stats` JSON

### manual_pages

- `id`
- `manual_id`
- `page_number`
- `text`
- `ocr_text`
- `image_path` nullable
- `extraction_quality` decimal

### manual_chunks

Searchable pieces of manual content.

- `id`
- `manual_id`
- `manual_page_id`
- `chunk_index`
- `heading`
- `content`
- `content_hash`
- `embedding` vector nullable
- `metadata` JSON

### diagnostic_entries

Approved diagnostic knowledge. This is the primary lookup table for new manual imports.

- `machine_id`
- `manual_id` nullable
- `manual_page_id` nullable
- `manual_chunk_id` nullable
- `manual_extraction_candidate_id` nullable
- `module_key` nullable, normalized context such as `PLUGSA`
- `section_title` nullable
- `primary_code` nullable, human-facing code when one exists
- `primary_code_normalized` nullable
- `context` JSON, for section/module/software/protocol context
- `identifiers` JSON, for flexible lookup identifiers
- `meaning`
- `cause`
- `severity`
- `recommended_action`
- `source_text`
- `source_page_number`
- `status`
- `approved_by`
- `approved_at`

Examples:

```json
{
  "module_key": "PLUGSA",
  "primary_code_normalized": "250",
  "context": {
    "module": "PLUGSA",
    "section_title": "PLUGSA - Plugin Safety Advanced"
  },
  "identifiers": {
    "code": "250"
  }
}
```

```json
{
  "module_key": "DTCJ1939",
  "context": {
    "section_title": "DTC J1939 - Servořízení rozvaděče pomocných okruhů"
  },
  "identifiers": {
    "sad_merlotool": "0X83",
    "sad_mps": "131",
    "spn": "520256",
    "fmi": "0"
  }
}
```

The rule is: new table shapes add new keys inside `context` or `identifiers`, not new database tables.

### diagnostic_aliases

Admin-managed normalization hints.

- `machine_id` nullable
- `alias_type`: `module`, `column`, `code_label`, etc.
- `alias_value`: raw value such as `PLUG_SA`
- `normalized_value`: canonical value such as `PLUGSA`
- `metadata`

### error_codes

Legacy rigid code catalog. New generalized imports should prefer `diagnostic_entries`.

Canonical code identity per machine.

- `id`
- `machine_id`
- `code`
- `normalized_code`
- `family`
- `is_active`

### error_code_definitions

Versioned meaning of a code.

- `id`
- `error_code_id`
- `manual_id`
- `manual_chunk_id` nullable
- `source_page_number`
- `title`
- `meaning`
- `cause`
- `severity` nullable
- `recommended_action`
- `effective_from_version_id` nullable
- `effective_to_version_id` nullable
- `supersedes_definition_id` nullable
- `source_confidence`
- `approval_status` enum: `candidate`, `approved`, `rejected`
- `approved_by`
- `approved_at`

## Diagnosis Tables

### diagnosis_requests

- `id`
- `machine_id`
- `user_id` nullable
- `software_version_id` nullable
- `screenshot_path`
- `status` enum: `uploaded`, `processing`, `needs_confirmation`, `resolved`, `failed`
- `raw_ocr_text`
- `selected_error_code_id` nullable
- `selected_definition_id` nullable
- `confidence`
- `result_payload` JSON
- `created_at`
- `updated_at`

### diagnosis_candidates

- `id`
- `diagnosis_request_id`
- `candidate_code`
- `normalized_code`
- `source` enum: `regex`, `ocr`, `gemini`, `manual_search`
- `confidence`
- `metadata` JSON
- `matched_error_code_id` nullable
- `matched_definition_id` nullable

## Admin and Auth Tables

Start with Laravel users and permissions:

- `users`
- `roles`
- `permissions`

Use roles:

- `super_admin`
- `content_admin`
- `technician`
- `viewer`

## Important Constraints

- Unique `machines.slug`.
- Unique `error_codes.machine_id + normalized_code`.
- Unique `manuals.file_hash` to avoid accidental duplicate upload.
- Definitions should not be physically deleted after publication; use statuses or replacements.
- Every approved definition should have a source manual or explicit admin note.

## Example Version Resolution

Given:

- Manual A is complete for software 1.0.
- Manual B is delta for software 1.2 and changes code `E1042`.
- Manual C is supplement with troubleshooting hints.

For a user on software 1.2:

- `E1042` should use the Manual B definition.
- Unchanged codes should still use Manual A definitions.
- Manual C can provide extra searchable context or recommended actions but should not automatically replace the authoritative definition.
