# Manual Ingestion Pipeline

Manuals are the main risk. The pipeline should be repeatable, auditable, and admin-reviewable.

## Upload

Admin uploads a PDF and selects:

- machine
- software version, if known
- coverage mode: complete, delta, or supplement
- manual language
- source notes

Laravel stores the original file and creates a `manual_import_runs` record. Extraction creates review suggestions, not approved error-code definitions.

## Extraction Stages

### 1. Basic PDF Inspection

Collect:

- page count
- file hash
- metadata
- whether text is embedded
- whether pages are image-heavy

System tools:

- `pdfinfo`
- `pdftotext`
- optional `pdfimages`

### 2. Text Extraction

Use Poppler `pdftotext` first. Store text per page.

If text is poor or missing, run OCR:

- render page image
- run Tesseract or OCRmyPDF
- store OCR text separately from embedded text

Keeping both text sources helps debug bad extraction.

### 3. Chunking

Split manuals into chunks that preserve source location:

- page number
- section heading if detected
- table boundaries if detected
- nearby text around error-code tables

Chunks should be small enough for search and AI extraction, but large enough to keep meaning. A good starting point is 500-1500 tokens per chunk with overlap.

### 4. Candidate Diagnostic Entry Detection

Use deterministic rules before AI, but store generic diagnostic candidates rather than fixed error-code rows:

- section/module context, for example `PLUGSA`
- flexible identifiers, for example `{ "code": "250" }`
- protocol identifiers, for example `{ "spn": "520256", "fmi": "0" }`
- meaning/action/source evidence

Deterministic extraction has two passes:

- `generic_section_table` for table-like blocks where a code starts or anchors a row
- `generic_text_reference` for prose such as "chyba 250 znamená..." or "SPN 520256 FMI 0..."

Store suggestions with source page, chunk, extractor name, confidence, and source text. Do not write directly to the approved error-code catalog.

Candidates also receive review metadata:

- `review_score`, used for default sorting and filtering
- `review_priority`: `high`, `normal`, or `low`
- `status=ignored` for obvious noise such as placeholder-only rows
- `noise_reason` for debug visibility

The default admin review queue should show actionable pending candidates, not every raw extraction artifact.

### 5. AI Structured Extraction

Use Gemini through Prism to turn chunks into generalized review suggestions. The prompt must not assume a fixed table format.

This pass is controlled by environment variables:

- `MANUAL_AI_EXTRACTION_ENABLED=false`
- `GEMINI_API_KEY=`
- `MANUAL_AI_MODEL=gemini-2.0-flash`
- `MANUAL_AI_MAX_CHUNKS_PER_IMPORT=40`
- `MANUAL_AI_MAX_CHUNK_CHARACTERS=6000`

The importer runs AI only on chunks that look diagnostically relevant, and only up to the configured chunk limit.

The prompt should require structured output like:

```json
{
  "entries": [
    {
      "context": {
        "module": "PLUGSA",
        "section_title": "PLUGSA - Plugin Safety Advanced"
      },
      "identifiers": {
        "code": "250"
      },
      "meaning": "Poloha a ovládací příkaz servoventilu pohybu vysunutí se neshodují",
      "recommended_action": "Zkontrolujte servořízení",
      "source_quote": "Short supporting excerpt only",
      "confidence": 0.86
    }
  ]
}
```

Do not publish these results automatically.

### 6. Embeddings

Generate embeddings for `manual_chunks` after text extraction.

Use these for:

- admin search inside manuals
- fallback user search when exact code lookup fails
- finding related diagnostic entries

### 7. Admin Review

Admin sees a simple review queue:

- extracted suggestions
- source manual and page
- detected conflicts with existing definitions
- suggested normalized code
- suggested meaning/cause/action

Admin can:

- approve
- edit and approve
- reject
- merge with existing code
- mark as superseding an older definition

Only approval creates or updates `diagnostic_entries`.

## Reruns

The pipeline should support rerunning:

- text extraction only
- AI extraction only
- embeddings only
- conflict detection only

Reruns should create new import run records and not erase approved data.

## Conflict Detection

Potential conflicts:

- same machine and normalized code has different meaning
- newer delta manual updates a code without superseding older definition
- same code appears in different manuals for different software versions
- AI extracted vague text that lacks source support

Conflicts should go to an admin queue.

## Failure Handling

Failures should be visible in admin with:

- stage that failed
- exception message
- file/page/chunk involved
- retry button

The upload should never disappear because processing failed.
