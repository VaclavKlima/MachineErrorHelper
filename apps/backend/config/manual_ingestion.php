<?php

return [
    'ai_extraction' => [
        'enabled' => env('MANUAL_AI_EXTRACTION_ENABLED', false),
        'provider' => env('MANUAL_AI_PROVIDER', 'gemini'),
        'model' => env('MANUAL_AI_MODEL', 'gemini-2.5-flash'),
        'max_chunks_per_import' => (int) env('MANUAL_AI_MAX_CHUNKS_PER_IMPORT', 40),
        'max_chunk_characters' => (int) env('MANUAL_AI_MAX_CHUNK_CHARACTERS', 6000),
        'min_confidence' => (float) env('MANUAL_AI_MIN_CONFIDENCE', 0.35),
    ],
];
