# Research Notes

Checked on 2026-04-16.

## Current Version Notes

- PHP 8.5 was released on 2025-11-20 and is currently the newest supported PHP branch.
- Laravel 13 documentation is current and includes vector search capabilities.
- Prism PHP is the selected AI abstraction for Gemini calls in this project.
- Filament 5 is current stable and requires PHP 8.2+, Laravel 11.28+, and Tailwind CSS 4.1+.
- Expo supports universal apps for Android, iOS, and web from one codebase.
- Gemini supports image understanding and structured JSON output, which fits screenshot code extraction.

## Important Sources

- PHP 8.5 release: https://www.php.net/releases/8.5/
- PHP supported versions: https://www.php.net/supported-versions.php
- Laravel 13 changelog: https://laravel.com/docs/changelog
- Prism PHP: https://prismphp.com/
- Laravel search and vector search: https://laravel.com/docs/13.x/search
- Laravel AI product page: https://laravel.com/ai
- Filament 5 installation: https://filamentphp.com/docs/5.x/introduction/installation/
- Expo web support: https://docs.expo.dev/workflow/web/
- Expo core concepts: https://docs.expo.dev/core-concepts/
- Gemini image understanding: https://ai.google.dev/gemini-api/docs/image-understanding
- Gemini structured output: https://ai.google.dev/gemini-api/docs/structured-output
- Gemini document processing: https://ai.google.dev/gemini-api/docs/document-processing

## Key Research Conclusions

- Prism PHP is preferable to a narrow Gemini-only PHP package because it keeps provider integration and structured output in one Laravel-friendly layer while leaving room to swap or add providers later.
- PostgreSQL is preferable to MySQL for this app because manual chunks and semantic search are central to the product.
- Expo is preferable for the user UI because the same codebase can ship mobile apps and a web version.
- Filament is the pragmatic admin choice because it avoids custom CRUD work while still using Laravel and Tailwind.
