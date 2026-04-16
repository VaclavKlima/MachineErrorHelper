<?php

namespace App\Jobs;

use App\Models\Manual;
use App\Services\ManualImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class ProcessManualImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        public int $manualId,
    ) {
        $this->onQueue('manual-import');
    }

    public function handle(ManualImportService $importer): void
    {
        $manual = Manual::query()->findOrFail($this->manualId);

        $importer->processManual($manual);
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("manual-import-{$this->manualId}"))
                ->releaseAfter(300)
                ->expireAfter(7200),
        ];
    }

    public function failed(Throwable $throwable): void
    {
        Manual::query()
            ->whereKey($this->manualId)
            ->update(['status' => 'failed']);
    }
}
