<?php

namespace Tests\Feature;

use App\Filament\Resources\DiagnosticEntries\Pages\EditDiagnosticEntry;
use App\Models\CodeDocumentation;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class DiagnosticEntryAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_a_simplified_error_codes_table(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $machine = Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);
        DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/admin/diagnostic-entries')
            ->assertOk()
            ->assertSee('State')
            ->assertSee('Machine')
            ->assertSee('Module')
            ->assertSee('Code')
            ->assertSee('Manual')
            ->assertSee('Docs')
            ->assertDontSee('Search code')
            ->assertDontSee('Meaning')
            ->assertDontSee('Confidence')
            ->assertDontSee('Updated');
    }

    public function test_admin_can_attach_documentation_when_editing_an_error_code(): void
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
        $firstDocumentation = CodeDocumentation::query()->create([
            'title' => 'Servo overload recovery',
            'content' => $this->richParagraph('Check the servo load and restart the axis.'),
        ]);
        $secondDocumentation = CodeDocumentation::query()->create([
            'title' => 'Servo overload prevention',
            'content' => $this->richParagraph('Review load limits and thermal state.'),
        ]);

        Livewire::actingAs($admin)
            ->test(EditDiagnosticEntry::class, ['record' => $entry->getRouteKey()])
            ->assertFormFieldExists('codeDocumentations')
            ->fillForm(fn (array $state): array => [
                ...$state,
                'codeDocumentations' => [$firstDocumentation->id, $secondDocumentation->id],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            [$firstDocumentation->id, $secondDocumentation->id],
            $entry->fresh()->codeDocumentations()->pluck('code_documentations.id')->all(),
        );
    }

    public function test_admin_can_create_documentation_from_the_error_code_edit_form(): void
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

        Livewire::actingAs($admin)
            ->test(EditDiagnosticEntry::class, ['record' => $entry->getRouteKey()])
            ->assertFormComponentActionExists('codeDocumentations', 'createOption')
            ->mountFormComponentAction('codeDocumentations', 'createOption')
            ->assertFormComponentActionDataSet([
                'title' => 'MAIN - E1042',
            ])
            ->unmountFormComponentAction()
            ->callFormComponentAction('codeDocumentations', 'createOption', [
                'title' => 'New inline documentation',
                'content' => $this->richParagraph('Created directly from the error code edit form.'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $documentation = CodeDocumentation::query()
            ->where('title', 'New inline documentation')
            ->first();

        $this->assertNotNull($documentation);
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
