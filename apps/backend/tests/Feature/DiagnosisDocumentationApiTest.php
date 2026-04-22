<?php

namespace Tests\Feature;

use App\Models\CodeDocumentation;
use App\Models\DiagnosisCandidate;
use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DiagnosisDocumentationApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_diagnosis_detail_includes_documentation_for_selected_and_candidate_entries(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');
        $token = $user->createToken('test-token')->plainTextToken;

        $machine = Machine::query()->create([
            'name' => 'Plug SA',
            'slug' => 'plug-sa',
            'is_active' => true,
        ]);

        $selectedEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => '250',
            'primary_code_normalized' => '250',
            'identifiers' => ['code' => '250'],
            'title' => 'Primary overload',
            'meaning' => 'Primary overload detected.',
            'status' => 'active',
        ]);

        $candidateEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => '251',
            'primary_code_normalized' => '251',
            'identifiers' => ['code' => '251'],
            'title' => 'Secondary overload',
            'meaning' => 'Secondary overload detected.',
            'status' => 'active',
        ]);

        $selectedDocumentation = CodeDocumentation::query()->create([
            'title' => 'Selected code documentation',
            'content' => $this->richParagraph('Check the main feed before resetting the machine.'),
        ]);
        $candidateDocumentation = CodeDocumentation::query()->create([
            'title' => 'Candidate code documentation',
            'content' => $this->richParagraph('Inspect the secondary overload trip.'),
        ]);

        $selectedDocumentation->diagnosticEntries()->attach($selectedEntry);
        $candidateDocumentation->diagnosticEntries()->attach($candidateEntry);

        $diagnosis = DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $selectedEntry->id,
            'status' => 'resolved',
        ]);

        DiagnosisCandidate::query()->create([
            'diagnosis_request_id' => $diagnosis->id,
            'candidate_code' => '251',
            'normalized_code' => '251',
            'source' => 'manual_entry',
            'confidence' => 0.87,
            'matched_diagnostic_entry_id' => $candidateEntry->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/diagnoses/'.$diagnosis->public_id)
            ->assertOk()
            ->assertJsonPath('data.selected_diagnostic_entry.code_documentations.0.title', 'Selected code documentation')
            ->assertJsonPath('data.candidates.0.matched_diagnostic_entry.code_documentations.0.title', 'Candidate code documentation');
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

    /**
     * @return array<string, mixed>
     */
    private function richParagraph(string $text): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => $text,
                        ],
                    ],
                ],
            ],
        ];
    }
}
