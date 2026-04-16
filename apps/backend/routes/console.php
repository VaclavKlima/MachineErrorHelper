<?php

use App\Models\Machine;
use App\Services\ManualImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('manuals:import {path : PDF path, relative to repo root or backend} {--machine= : Machine ID or slug} {--title= : Manual title} {--language=cs : Manual language code} {--coverage-mode=complete : complete, delta, or supplement}', function (ManualImportService $importer) {
    $machineOption = $this->option('machine');

    if ($machineOption) {
        $machine = Machine::query()
            ->whereKey($machineOption)
            ->orWhere('slug', $machineOption)
            ->first();
    } else {
        $machines = Machine::query()->get();

        if ($machines->count() !== 1) {
            $this->error('Pass --machine=ID or --machine=slug when the database does not contain exactly one machine.');

            return 1;
        }

        $machine = $machines->first();
    }

    if (! $machine) {
        $this->error('Machine was not found. Pass --machine=ID or --machine=slug.');

        return 1;
    }

    $manual = $importer->importFromPath(
        machine: $machine,
        sourcePath: $this->argument('path'),
        title: $this->option('title'),
        language: $this->option('language'),
        coverageMode: $this->option('coverage-mode'),
        sourceNotes: 'Imported from local assets/manuals folder.',
    );

    $manual->loadCount(['pages', 'chunks']);
    $suggestionCount = $manual->extractionCandidates()->where('status', 'pending')->count();

    $this->info("Imported manual #{$manual->id}: {$manual->title}");
    $this->line("Pages: {$manual->pages_count}");
    $this->line("Chunks: {$manual->chunks_count}");
    $this->line("Suggestions pending review: {$suggestionCount}");

    return 0;
})->purpose('Import a local PDF manual, extract text pages/chunks, and create candidate error-code definitions');
