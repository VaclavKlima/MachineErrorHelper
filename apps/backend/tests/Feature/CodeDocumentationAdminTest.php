<?php

namespace Tests\Feature;

use App\Models\CodeDocumentation;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CodeDocumentationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_code_documentation_pages(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $documentation = CodeDocumentation::query()->create([
            'title' => 'Servo overload recovery',
            'content' => $this->richParagraph('Check the servo load and restart the axis.'),
        ]);

        $this->actingAs($admin)
            ->get('/admin/code-documentations')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/code-documentations/create')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/code-documentations/'.$documentation->id)
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/code-documentations/'.$documentation->id.'/edit')
            ->assertOk();
    }

    public function test_documentation_can_link_codes_from_multiple_machines(): void
    {
        $firstMachine = Machine::query()->create([
            'name' => 'Laser cutter',
            'slug' => 'laser-cutter',
            'is_active' => true,
        ]);
        $secondMachine = Machine::query()->create([
            'name' => 'Hydraulic press',
            'slug' => 'hydraulic-press',
            'is_active' => true,
        ]);

        $firstEntry = DiagnosticEntry::query()->create([
            'machine_id' => $firstMachine->id,
            'module_key' => 'SERVO',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);
        $secondEntry = DiagnosticEntry::query()->create([
            'machine_id' => $secondMachine->id,
            'module_key' => 'PUMP',
            'primary_code' => 'A-17',
            'primary_code_normalized' => 'A17',
            'identifiers' => ['code' => 'A17'],
            'title' => 'Hydraulic pressure drop',
            'meaning' => 'Pump pressure is below threshold.',
            'status' => 'active',
        ]);

        $documentation = CodeDocumentation::query()->create([
            'title' => 'Shared startup diagnostics',
            'content' => $this->richParagraph('Inspect shared electrical and hydraulic prerequisites before deeper repair work.'),
        ]);

        $documentation->diagnosticEntries()->sync([$firstEntry->id, $secondEntry->id]);

        $this->assertCount(2, $documentation->diagnosticEntries()->get());
        $this->assertDatabaseHas('code_documentation_diagnostic_entry', [
            'code_documentation_id' => $documentation->id,
            'diagnostic_entry_id' => $firstEntry->id,
        ]);
        $this->assertDatabaseHas('code_documentation_diagnostic_entry', [
            'code_documentation_id' => $documentation->id,
            'diagnostic_entry_id' => $secondEntry->id,
        ]);
    }

    public function test_documentation_search_can_match_multiple_terms_across_fields(): void
    {
        $machine = Machine::query()->create([
            'name' => 'Plug SA',
            'slug' => 'plug-sa',
            'is_active' => true,
        ]);

        $matchingEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'IO',
            'primary_code' => '250',
            'primary_code_normalized' => '250',
            'identifiers' => ['code' => '250'],
            'title' => 'Power supply alert',
            'meaning' => 'Power supply alert.',
            'status' => 'active',
        ]);

        DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'IO',
            'primary_code' => '251',
            'primary_code_normalized' => '251',
            'identifiers' => ['code' => '251'],
            'title' => 'Power supply alert',
            'meaning' => 'Another alert.',
            'status' => 'active',
        ]);

        $results = DiagnosticEntry::query()
            ->with('machine:id,name')
            ->searchForDocumentation('PLUGSA 250')
            ->get();

        $this->assertCount(1, $results);
        $this->assertTrue($results->first()->is($matchingEntry));
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
