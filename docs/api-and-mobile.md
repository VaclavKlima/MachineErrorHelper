# API and User Frontend

## API Style

Use Laravel JSON APIs consumed by the Expo app. The Expo app must run as both a browser webpage and a mobile application.

Authentication options:

- public/anonymous diagnosis for simple MVP
- Laravel Sanctum token auth if users need history
- admin authentication handled separately through Filament

## First API Endpoints

```text
GET    /api/machines
GET    /api/machines/{machine}
POST   /api/diagnoses
GET    /api/diagnoses/{diagnosis}
POST   /api/diagnoses/{diagnosis}/confirm-code
POST   /api/diagnoses/{diagnosis}/manual-code
```

## Diagnosis Creation

`POST /api/diagnoses`

Multipart form:

- `machine_id`
- `software_version_id` optional
- `screenshot`

Response:

```json
{
  "id": "01HX...",
  "status": "processing",
  "poll_url": "/api/diagnoses/01HX..."
}
```

## Diagnosis Polling Result

`GET /api/diagnoses/{diagnosis}`

Resolved response:

```json
{
  "status": "resolved",
  "detected_code": "E1042",
  "confidence": 0.93,
  "definition": {
    "title": "Servo overload",
    "meaning": "The servo motor exceeded allowed load",
    "cause": "Mechanical jam, overload, wrong parameter",
    "recommended_action": "Inspect mechanical load and reset"
  },
  "hints": [
    {
      "title": "Quick mechanical inspection",
      "steps": ["Power down the machine", "Inspect axis movement", "Clear obstruction"]
    }
  ],
  "source": {
    "manual_title": "Machine X Software 1.2 Alarm Manual",
    "page": 42
  }
}
```

Needs-confirmation response:

```json
{
  "status": "needs_confirmation",
  "candidates": [
    {"code": "E1042", "confidence": 0.78},
    {"code": "E1047", "confidence": 0.66}
  ]
}
```

## Expo App Structure

Use Expo Router.

Suggested screens:

```text
app/
  index.tsx                  machine selection
  diagnose/[machineId].tsx   upload/take screenshot
  result/[diagnosisId].tsx   polling and result
  manual-entry.tsx           typed code fallback
```

Suggested internal modules:

```text
src/api/client.ts
src/api/diagnoses.ts
src/api/machines.ts
src/components/MachinePicker.tsx
src/components/ScreenshotUploader.tsx
src/components/DiagnosisResult.tsx
src/features/diagnosis/
```

## Mobile UX Notes

- Keep the browser version fully usable, not a secondary preview.
- Use one clean dark theme with machining/workshop styling and yellow/orange safety accents.
- Make manual code entry visible, not hidden.
- Upload flow should accept existing screenshots and camera photos.
- Show processing state with polling.
- Cache machine list locally.
- Store recent diagnosis on device if user is anonymous.
- Keep result pages shareable if business rules allow it.

## Why Not Blade/Inertia for User UI

Blade or Inertia would be fine for a web-only product, but this user interface should also become a mobile app. Expo keeps one user frontend for browser web, Android, and iOS while Laravel stays focused on API, admin, queues, and data.
