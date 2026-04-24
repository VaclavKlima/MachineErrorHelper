<?php

use App\Models\Machine;
use App\Models\User;
use App\Services\ManualImportService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

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
    $publishedCodeCount = $manual->diagnosticEntries()->where('status', 'active')->count();

    $this->info("Imported manual #{$manual->id}: {$manual->title}");
    $this->line("Pages: {$manual->pages_count}");
    $this->line("Chunks: {$manual->chunks_count}");
    $this->line("Active codes: {$publishedCodeCount}");

    return 0;
})->purpose('Import a local PDF manual, extract text pages/chunks, and publish active error-code definitions');

Artisan::command('admin:create-default-user {--name= : Admin display name} {--email= : Admin email address} {--password= : Admin password}', function () {
    $isLocalBootstrap = app()->isLocal() || app()->runningUnitTests();

    $name = $this->option('name') ?: ($isLocalBootstrap ? 'Admin' : null);
    $email = $this->option('email') ?: ($isLocalBootstrap ? 'admin@example.com' : null);
    $password = $this->option('password') ?: ($isLocalBootstrap ? 'password' : null);

    if (! $name && $this->input->isInteractive()) {
        $name = $this->ask('Admin name');
    }

    if (! $email && $this->input->isInteractive()) {
        $email = $this->ask('Admin email');
    }

    if (! $password && $this->input->isInteractive()) {
        $password = $this->secret('Admin password');
    }

    if (! $name || ! $email || ! $password) {
        $this->error('Pass --name, --email, and --password, or run the command locally to use the development defaults.');

        return 1;
    }

    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('The admin email must be a valid email address.');

        return 1;
    }

    Role::findOrCreate('admin', 'web');

    $existingUser = User::query()->where('email', $email)->first();

    $user = DB::transaction(function () use ($existingUser, $name, $email, $password): User {
        $user = $existingUser ?? new User();

        $user->fill([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
        }

        $user->save();
        $user->syncRoles(['admin']);

        return $user->fresh();
    });

    $this->info(($existingUser ? 'Updated' : 'Created')." admin user #{$user->id}.");
    $this->line("Email: {$user->email}");

    if ($isLocalBootstrap && $password === 'password') {
        $this->warn('Local development default password: password');
    }

    return 0;
})->purpose('Create or update an admin user with the admin role required for Filament access');
