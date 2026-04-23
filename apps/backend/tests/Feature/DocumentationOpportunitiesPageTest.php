<?php

namespace Tests\Feature;

use App\Filament\Widgets\DocumentationOpportunityStats;
use App\Filament\Widgets\MissingDocumentationCodesTable;
use App\Models\CodeDocumentation;
use App\Models\DiagnosisRequest;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DocumentationOpportunitiesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_documentation_opportunities_page(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');

        $this->actingAs($admin)
            ->get('/admin/documentation-opportunities')
            ->assertOk()
            ->assertSee('Documentation opportunities');

        Livewire::actingAs($admin)
            ->test(DocumentationOpportunityStats::class)
            ->assertSee('Total scans')
            ->assertSee('Documented codes')
            ->assertSee('Missing docs');
    }

    public function test_documentation_widgets_are_not_shown_on_the_main_dashboard(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertDontSee('Documentation dashboard')
            ->assertDontSee('Code usage');
    }

    public function test_missing_documentation_widget_prioritizes_used_codes_without_documentation(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $missingDocumentationEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);
        $documentedEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'PUMP',
            'primary_code' => 'A17',
            'primary_code_normalized' => 'A17',
            'identifiers' => ['code' => 'A17'],
            'title' => 'Pressure drop',
            'meaning' => 'Pressure drop detected.',
            'status' => 'active',
        ]);
        $documentation = CodeDocumentation::query()->create([
            'title' => 'Pressure drop recovery',
            'content' => $this->richParagraph('Inspect the pump and restore pressure.'),
        ]);
        $documentedEntry->codeDocumentations()->sync([$documentation->id]);

        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $missingDocumentationEntry->id,
            'status' => 'resolved',
        ]);
        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $missingDocumentationEntry->id,
            'status' => 'resolved',
        ]);
        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $documentedEntry->id,
            'status' => 'resolved',
        ]);

        Livewire::actingAs($admin)
            ->test(MissingDocumentationCodesTable::class)
            ->assertTableHeaderActionsExistInOrder([
                'showMissingDocumentation',
                'showAllCodeUsage',
                'showDocumentedCodes',
            ])
            ->assertCountTableRecords(1)
            ->assertCanSeeTableRecords([$missingDocumentationEntry])
            ->assertCanNotSeeTableRecords([$documentedEntry])
            ->assertSee('2');
    }

    public function test_documentation_widget_can_switch_to_all_used_codes(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $missingDocumentationEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);
        $documentedEntry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'PUMP',
            'primary_code' => 'A17',
            'primary_code_normalized' => 'A17',
            'identifiers' => ['code' => 'A17'],
            'title' => 'Pressure drop',
            'meaning' => 'Pressure drop detected.',
            'status' => 'active',
        ]);
        $documentation = CodeDocumentation::query()->create([
            'title' => 'Pressure drop recovery',
            'content' => $this->richParagraph('Inspect the pump and restore pressure.'),
        ]);
        $documentedEntry->codeDocumentations()->sync([$documentation->id]);

        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $missingDocumentationEntry->id,
            'status' => 'resolved',
        ]);
        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $documentedEntry->id,
            'status' => 'resolved',
        ]);

        Livewire::actingAs($admin)
            ->test(MissingDocumentationCodesTable::class)
            ->callTableAction('showAllCodeUsage')
            ->assertCountTableRecords(2)
            ->assertCanSeeTableRecords([$missingDocumentationEntry, $documentedEntry])
            ->assertTableColumnStateSet('code_documentations_count', 1, $documentedEntry);
    }

    public function test_admin_can_create_documentation_from_documentation_opportunities(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $entry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);

        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $entry->id,
            'status' => 'resolved',
        ]);

        Livewire::actingAs($admin)
            ->test(MissingDocumentationCodesTable::class)
            ->callTableAction('createDocumentationForCode', $entry, [
                'title' => 'MAIN - E1042',
                'content' => $this->richParagraph('Created from documentation opportunities.'),
            ])
            ->assertHasNoErrors();

        $documentation = CodeDocumentation::query()
            ->where('title', 'MAIN - E1042')
            ->first();

        $this->assertNotNull($documentation);
        $this->assertSame(
            [$documentation->id],
            $entry->fresh()->codeDocumentations()->pluck('code_documentations.id')->all(),
        );
    }

    public function test_admin_can_attach_existing_documentation_from_documentation_opportunities(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        $entry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);
        $documentation = CodeDocumentation::query()->create([
            'title' => 'Servo overload recovery',
            'content' => $this->richParagraph('Check the servo load and restart the axis.'),
        ]);

        DiagnosisRequest::query()->create([
            'machine_id' => $machine->id,
            'selected_diagnostic_entry_id' => $entry->id,
            'status' => 'resolved',
        ]);

        Livewire::actingAs($admin)
            ->test(MissingDocumentationCodesTable::class)
            ->callTableAction('attachExistingDocumentation', $entry, [
                'documentation_ids' => [$documentation->id],
            ])
            ->assertHasNoErrors();

        $this->assertSame(
            [$documentation->id],
            $entry->fresh()->codeDocumentations()->pluck('code_documentations.id')->all(),
        );
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
