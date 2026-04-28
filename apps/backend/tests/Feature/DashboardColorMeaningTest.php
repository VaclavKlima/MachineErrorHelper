<?php

namespace Tests\Feature;

use App\Models\DashboardColorMeaning;
use App\Models\DiagnosisRequest;
use App\Models\Machine;
use App\Models\User;
use App\Services\DashboardColorAliasGenerator;
use App\Services\ScreenshotDiagnosticExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardColorMeaningTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_ai_aliases_from_admin_selected_hex_color(): void
    {
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);

        $meaning = DashboardColorMeaning::query()->create([
            'machine_id' => $machine->id,
            'label' => 'Critical',
            'hex_color' => '#D72828',
            'description' => 'Stop work and check the recommended repair steps.',
            'priority' => 10,
            'is_active' => true,
        ]);

        $this->assertSame('#D72828', $meaning->hex_color);
        $this->assertSame('critical', $meaning->ai_key);
        $this->assertContains('red', $meaning->ai_aliases);
        $this->assertContains('strong red', $meaning->ai_aliases);
    }

    public function test_ai_key_is_regenerated_from_label_when_label_changes(): void
    {
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);

        $meaning = DashboardColorMeaning::query()->create([
            'machine_id' => $machine->id,
            'label' => 'Past error',
            'hex_color' => '#E5E7EB',
            'priority' => 10,
            'is_active' => true,
        ]);

        $meaning->update(['label' => 'Historical warning']);

        $this->assertSame('historical_warning', $meaning->fresh()->ai_key);
    }

    public function test_alias_generator_names_common_dashboard_colors(): void
    {
        $generator = new DashboardColorAliasGenerator;

        $this->assertContains('amber', $generator->aliasesForHex('#F59E0B'));
        $this->assertContains('light gray', $generator->aliasesForHex('#E5E7EB'));
    }

    public function test_screenshot_candidates_keep_matched_color_meaning_for_end_user(): void
    {
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $meaning = DashboardColorMeaning::query()->create([
            'machine_id' => $machine->id,
            'label' => 'Critical',
            'hex_color' => '#D72828',
            'description' => 'Machine has an active critical fault.',
            'priority' => 10,
            'is_active' => true,
        ]);
        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'screenshot_path' => 'diagnosis-screenshots/test.png',
            'status' => 'uploaded',
        ]);

        $created = (new ScreenshotDiagnosticExtractionService)->storeCandidates($diagnosis, [
            'module_key' => 'MAIN',
            'controller_identifier' => null,
            'software_version' => null,
            'serial_number' => null,
            'errors' => [
                [
                    'code' => '250',
                    'observed_color' => 'dark red badge',
                    'color_status_key' => 'critical',
                    'color_status_confidence' => 0.91,
                    'confidence' => 0.95,
                ],
            ],
        ]);

        $candidate = $created[0]->fresh();

        $this->assertSame($meaning->id, $candidate->dashboard_color_meaning_id);
        $this->assertSame('critical', $candidate->metadata['color_status_key']);
        $this->assertSame('Critical', $candidate->metadata['color_status_label']);
        $this->assertSame('Machine has an active critical fault.', $candidate->metadata['color_status_description']);
    }

    public function test_manual_code_confirmation_can_preserve_selected_color_meaning(): void
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('app_user');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $meaning = DashboardColorMeaning::query()->create([
            'machine_id' => $machine->id,
            'label' => 'Warning',
            'hex_color' => '#F59E0B',
            'description' => 'Machine reports a warning condition.',
            'priority' => 20,
            'is_active' => true,
        ]);
        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $user->id,
            'status' => 'needs_confirmation',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/diagnoses/'.$diagnosis->public_id.'/manual-code', [
                'module_key' => 'MAIN',
                'entries' => [
                    [
                        'code' => '250',
                        'dashboard_color_meaning_id' => $meaning->id,
                    ],
                ],
            ])
            ->assertOk();

        $candidate = $diagnosis->candidates()->firstOrFail();

        $this->assertSame($meaning->id, $candidate->dashboard_color_meaning_id);
        $this->assertSame('warning', $candidate->metadata['color_status_key']);
        $this->assertSame('Warning', $candidate->metadata['color_status_label']);
        $this->assertSame('Machine reports a warning condition.', $candidate->metadata['color_status_description']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses/'.$diagnosis->public_id)
            ->assertOk()
            ->assertJsonPath('data.candidates.0.dashboard_color_meaning.priority', 20);
    }

    public function test_diagnosis_detail_includes_current_machine_color_meanings(): void
    {
        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => Hash::make('password'),
        ]);
        $user->assignRole('app_user');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        DashboardColorMeaning::query()->create([
            'machine_id' => $machine->id,
            'label' => 'Recently added',
            'hex_color' => '#22C55E',
            'description' => 'Added after the user opened the app.',
            'priority' => 30,
            'is_active' => true,
        ]);
        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $user->id,
            'status' => 'needs_confirmation',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/diagnoses/'.$diagnosis->public_id)
            ->assertOk()
            ->assertJsonPath('data.machine.dashboard_color_meanings.0.label', 'Recently added')
            ->assertJsonPath('data.machine.dashboard_color_meanings.0.description', 'Added after the user opened the app.');
    }
}
