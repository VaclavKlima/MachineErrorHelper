<?php

namespace Tests\Feature;

use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\Manual;
use App\Models\ManualExtractionCandidate;
use App\Services\ManualExtractionCandidatePublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualExtractionCandidatePublishingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_publishes_an_extracted_candidate_as_an_active_code(): void
    {
        [$machine, $manual] = $this->createMachineAndManual();

        $candidate = ManualExtractionCandidate::query()->create([
            'machine_id' => $machine->id,
            'manual_id' => $manual->id,
            'candidate_type' => 'diagnostic_entry',
            'code' => 'E 1042',
            'normalized_code' => 'E1042',
            'family' => 'MAIN',
            'module_key' => 'MAIN',
            'primary_code' => 'E 1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'source_page_number' => 12,
            'extractor' => 'generic_section_table',
            'confidence' => 0.91,
            'status' => 'pending',
        ]);

        $entry = app(ManualExtractionCandidatePublishingService::class)->publish($candidate);

        $this->assertNotNull($entry);
        $this->assertSame('active', $entry->status);
        $this->assertSame('published', $candidate->refresh()->status);

        $this->assertDatabaseHas('diagnostic_entries', [
            'machine_id' => $machine->id,
            'manual_id' => $manual->id,
            'module_key' => 'MAIN',
            'primary_code_normalized' => 'E1042',
            'status' => 'active',
        ]);
    }

    public function test_it_does_not_reenable_a_disabled_code_during_reimport(): void
    {
        [$machine, $manual] = $this->createMachineAndManual();

        DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'manual_id' => $manual->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Old title',
            'meaning' => 'Old meaning.',
            'status' => 'disabled',
        ]);

        $candidate = ManualExtractionCandidate::query()->create([
            'machine_id' => $machine->id,
            'manual_id' => $manual->id,
            'candidate_type' => 'diagnostic_entry',
            'code' => 'E1042',
            'normalized_code' => 'E1042',
            'family' => 'MAIN',
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Updated title',
            'meaning' => 'Updated meaning.',
            'source_page_number' => 12,
            'extractor' => 'generic_section_table',
            'confidence' => 0.91,
            'status' => 'pending',
        ]);

        $entry = app(ManualExtractionCandidatePublishingService::class)->publish($candidate);

        $this->assertNotNull($entry);
        $this->assertSame('disabled', $entry->status);
        $this->assertSame('Updated meaning.', $entry->meaning);
        $this->assertSame(1, DiagnosticEntry::query()->count());
    }

    /**
     * @return array{0: Machine, 1: Manual}
     */
    private function createMachineAndManual(): array
    {
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);

        $manual = Manual::query()->create([
            'machine_id' => $machine->id,
            'title' => 'Test manual',
            'coverage_mode' => 'complete',
            'language' => 'en',
            'file_path' => 'manuals/test.pdf',
            'file_hash' => hash('sha256', 'test-manual'),
            'status' => 'uploaded',
        ]);

        return [$machine, $manual];
    }
}
