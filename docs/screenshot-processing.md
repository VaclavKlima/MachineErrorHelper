# Screenshot Processing

The screenshot flow should be fast for the user but careful about uncertainty.

## User Flow

1. User selects a machine.
2. User uploads a screenshot or takes a photo.
3. API creates a `diagnosis_requests` record.
4. Queue job preprocesses the image.
5. OCR and Gemini extract candidate codes.
6. Backend matches candidates to approved diagnostic entries.
7. API returns either a result or a request for confirmation.

## Image Preprocessing

Before OCR/AI:

- normalize orientation
- resize if too large
- create high-contrast copy
- optionally crop likely dashboard region
- store derived images for admin debugging

Use `intervention/image-laravel` and PHP image extensions for this.

## Code Extraction Strategy

Use multiple extractors:

### Regex Over OCR Text

Fast and cheap.

Process:

- run Tesseract OCR
- normalize whitespace and confusing characters
- apply machine-specific regex patterns
- produce candidates

### Gemini Vision

Use when:

- OCR result is empty
- OCR returns multiple confusing candidates
- dashboard layout matters
- user photo is angled or blurry

Gemini should return structured output with code candidates and confidence.

Current Merlo dashboard prompt guidance:

- Read the top green header as `MODULE - controller/id`.
- Use only the text before the dash as `module_key`, for example `PLUG_SA`, `UGSS_S`, `UGM`, or `UCTI`.
- Store the text after the dash as `controller_identifier`, for example `CU533`, `117006`, `14871`, or `124512`.
- Treat `SW:` and `SN:` rows as metadata, never as diagnostic codes.
- Extract active errors from the small colored badges under `List of errors` in the blue panel.
- Preserve leading zeroes in badges, for example `002`, `003`, `007`, `011`, `022`, and `029`.
- Return every visible badge as a separate error, including low-confidence badges affected by glare or angle.

### Manual Search Fallback

If no exact code is found:

- search manual chunks using OCR/dashboard text
- use semantic search to find likely relevant sections
- show "possible matches" instead of a definitive result

## Matching Rules

1. Extract context and identifiers, for example `PLUG_SA`, `SW:1.5.0`, `250`.
2. Normalize aliases, for example `PLUG_SA` to `PLUGSA`.
3. Exact structured match against approved `diagnostic_entries`.
4. If user provided software version, prefer entries effective for that version.
5. If a module is known or manually confirmed, do not fall back to a code-only match from another module.
6. If multiple close candidates exist, ask user to confirm the module/code.
7. If no structured match is reliable, show manual search results and ask user to upload a clearer image or enter the code manually.

## Confidence

Confidence should combine:

- OCR confidence if available
- Gemini confidence
- regex/pattern strength
- whether exact match exists
- whether multiple conflicting definitions exist

Example:

- 0.90+: show direct answer
- 0.60-0.89: show likely answer with confirmation option
- below 0.60: ask for manual confirmation or typed code

## Manual Code Entry

The app should allow manual code entry from the beginning.

This is important because:

- screenshots may be blurry
- some dashboards use small fonts
- users may be in urgent repair situations
- exact typed code is cheaper and more reliable than AI extraction

## Result Page

Show:

- detected code
- meaning
- likely cause
- recommended action
- admin hint/tutorial
- safety warning if present
- source manual and page
- "this may differ by software version" if needed

Do not show raw AI reasoning to end users.
