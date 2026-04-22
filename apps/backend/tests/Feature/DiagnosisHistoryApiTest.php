<?php

namespace Tests\Feature;

use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiagnosisHistoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_fetch_their_scan_history_ordered_from_newest_to_oldest(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');
        $otherUser = $this->createUser('other@example.com', 'app_user');
        $token = $user->createToken('test-token')->plainTextToken;

        $machine = Machine::query()->create([
            'name' => 'Plug SA',
            'slug' => 'plug-sa',
            'is_active' => true,
        ]);

        $entry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'PLUGSA',
            'primary_code' => '250',
            'primary_code_normalized' => '250',
            'identifiers' => ['code' => '250'],
            'title' => 'Pressure warning',
            'meaning' => 'Pressure warning.',
            'status' => 'active',
        ]);

        $olderDiagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $user->id,
            'selected_diagnostic_entry_id' => $entry->id,
            'status' => 'resolved',
            'ai_detected_codes' => ['250'],
            'user_entered_codes' => ['250'],
        ]);
        $olderDiagnosis->forceFill([
            'created_at' => Carbon::parse('2026-04-20 09:15:00'),
            'updated_at' => Carbon::parse('2026-04-20 09:15:00'),
        ])->save();

        $newerDiagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $user->id,
            'status' => 'needs_confirmation',
            'ai_detected_codes' => ['251', '252'],
            'user_entered_codes' => [],
        ]);
        $newerDiagnosis->forceFill([
            'created_at' => Carbon::parse('2026-04-21 17:45:00'),
            'updated_at' => Carbon::parse('2026-04-21 17:45:00'),
        ])->save();

        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'user_id' => $otherUser->id,
            'status' => 'resolved',
            'ai_detected_codes' => ['999'],
            'user_entered_codes' => ['999'],
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/diagnoses/history')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newerDiagnosis->public_id)
            ->assertJsonPath('data.0.machine.name', 'Plug SA')
            ->assertJsonPath('data.0.ai_detected_codes.0', '251')
            ->assertJsonPath('data.1.id', $olderDiagnosis->public_id)
            ->assertJsonPath('data.1.selected_diagnostic_entry.primary_code', '250');
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
