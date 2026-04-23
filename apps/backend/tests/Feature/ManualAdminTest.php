<?php

namespace Tests\Feature;

use App\Filament\Resources\Manuals\Pages\ViewManual;
use App\Filament\Resources\Manuals\RelationManagers\ErrorCodesRelationManager;
use App\Models\DiagnosticEntry;
use App\Models\Machine;
use App\Models\Manual;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class ManualAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_manual_pages_without_software_version_field(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
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
        $entry = DiagnosticEntry::query()->create([
            'machine_id' => $machine->id,
            'manual_id' => $manual->id,
            'module_key' => 'MAIN',
            'primary_code' => 'E1042',
            'primary_code_normalized' => 'E1042',
            'identifiers' => ['code' => 'E1042'],
            'source_page_number' => 12,
            'title' => 'Servo overload',
            'meaning' => 'Servo overload detected.',
            'status' => 'active',
        ]);

        $this->actingAs($admin)
            ->get('/admin/manuals')
            ->assertOk()
            ->assertDontSee('Software version')
            ->assertDontSee('File path')
            ->assertDontSee('File hash')
            ->assertDontSee('Extracted pages')
            ->assertDontSee('Extracted codes');

        $this->actingAs($admin)
            ->get('/admin/manuals/create')
            ->assertOk()
            ->assertDontSee('Software version');

        $this->actingAs($admin)
            ->get('/admin/manuals/'.$manual->id)
            ->assertOk()
            ->assertDontSee('Software version');

        $this->actingAs($admin)
            ->get('/admin/manuals/'.$manual->id.'/edit')
            ->assertOk()
            ->assertDontSee('Software version');

        Livewire::actingAs($admin)
            ->test(ErrorCodesRelationManager::class, [
                'ownerRecord' => $manual,
                'pageClass' => ViewManual::class,
            ])
            ->assertTableActionVisible('edit', $entry)
            ->assertSee('E1042')
            ->assertSee('Servo overload')
            ->assertSee('MAIN');
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
