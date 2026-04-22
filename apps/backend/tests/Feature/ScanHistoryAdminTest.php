<?php

namespace Tests\Feature;

use App\Jobs\ProcessDiagnosisScreenshot;
use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use App\Services\ScreenshotDiagnosticExtractionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ScanHistoryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_request_is_stored_with_user_and_manual_codes_snapshot(): void
    {
        Storage::fake('local');
        Queue::fake();

        $user = $this->createUser('operator@example.com', 'app_user');
        $machine = Machine::query()->create([
            'name' => 'Plug SA',
            'slug' => 'plug-sa',
            'is_active' => true,
        ]);
        DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'PLUGSA',
            'primary_code' => '250',
            'primary_code_normalized' => '250',
            'identifiers' => ['code' => '250'],
            'title' => 'Pressure warning',
            'meaning' => 'Pressure warning.',
            'status' => 'active',
        ]);

        $create = $this->actingAs($user, 'sanctum')
            ->postJson('/api/diagnoses', [
                'machine_id' => $machine->id,
                'screenshot' => UploadedFile::fake()->create('scan.png', 32, 'image/png'),
            ]);

        $create->assertCreated();

        $diagnosis = DiagnosisRequest::query()->firstOrFail();

        $this->assertSame($user->id, $diagnosis->user_id);
        $this->assertSame([], $diagnosis->ai_detected_codes ?? []);
        $this->assertSame([], $diagnosis->user_entered_codes ?? []);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/diagnoses/'.$diagnosis->public_id.'/manual-code', [
                'code' => '250',
                'module_key' => 'PLUGSA',
            ])
            ->assertOk();

        $this->assertSame(['250'], $diagnosis->fresh()->user_entered_codes);
    }

    public function test_screenshot_processing_stores_ai_detected_codes_snapshot(): void
    {
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => '250',
            'primary_code_normalized' => '250',
            'identifiers' => ['code' => '250'],
            'title' => 'Pressure warning',
            'meaning' => 'Pressure warning.',
            'status' => 'active',
        ]);

        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'screenshot_path' => 'diagnosis-screenshots/test.png',
            'status' => 'uploaded',
        ]);

        $extractor = $this->createMock(ScreenshotDiagnosticExtractionService::class);
        $extractor->expects($this->once())
            ->method('extract')
            ->with($this->callback(fn ($argument): bool => $argument instanceof DiagnosisRequest && $argument->is($diagnosis)))
            ->willReturn([
                'module_key' => 'MAIN',
                'controller_identifier' => null,
                'software_version' => null,
                'serial_number' => null,
                'raw_text' => '250',
                'errors' => [
                    ['code' => '250', 'confidence' => 0.92],
                    ['code' => '011', 'confidence' => 0.71],
                ],
            ]);
        $extractor->expects($this->once())
            ->method('storeCandidates')
            ->with(
                $this->callback(fn ($argument): bool => $argument instanceof DiagnosisRequest && $argument->is($diagnosis)),
                $this->callback(fn ($argument): bool => is_array($argument)),
            )
            ->willReturn([]);

        (new ProcessDiagnosisScreenshot($diagnosis->id))->handle($extractor);

        $this->assertSame(['250', '011'], $diagnosis->fresh()->ai_detected_codes);
    }

    public function test_admin_can_view_scan_history_pages(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $user = $this->createUser('operator@example.com', 'app_user');

        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $user->id,
            'screenshot_path' => 'diagnosis-screenshots/test.png',
            'status' => 'resolved',
            'ai_detected_codes' => ['250'],
            'user_entered_codes' => ['250', '011'],
        ]);

        $this->actingAs($admin)
            ->get('/admin/diagnosis-requests')
            ->assertOk()
            ->assertSee('Scan history');

        $this->actingAs($admin)
            ->get('/admin/diagnosis-requests/'.$diagnosis->public_id)
            ->assertOk()
            ->assertSee('250');
    }

    private function createUser(string $email, string $role): User
    {
        $user = User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
