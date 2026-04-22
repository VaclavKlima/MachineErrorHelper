<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_register_and_access_app_api(): void
    {
        Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);

        $register = $this->postJson('/api/register', [
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $register->assertCreated()
            ->assertJsonPath('data.user.email', 'operator@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $user = User::query()->where('email', 'operator@example.com')->firstOrFail();

        $this->assertTrue($user->hasRole('app_user'));
        $this->assertFalse($user->hasRole('admin'));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));

        $this->withHeader('Authorization', 'Bearer '.$register->json('data.token'))
            ->getJson('/api/machines')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'test-machine');
    }

    public function test_registration_requires_unique_email_and_password_confirmation(): void
    {
        $this->createUser('operator@example.com', 'app_user');

        $this->postJson('/api/register', [
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_app_user_can_login_and_access_app_api(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');

        Machine::query()->create([
            'name' => 'Test machine',
            'slug' => 'test-machine',
            'is_active' => true,
        ]);

        $login = $this->postJson('/api/login', [
            'email' => 'operator@example.com',
            'password' => 'password',
        ]);

        $login->assertOk()
            ->assertJsonPath('data.user.email', 'operator@example.com')
            ->assertJsonStructure(['data' => ['token']]);

        $this->withHeader('Authorization', 'Bearer '.$login->json('data.token'))
            ->getJson('/api/machines')
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'test-machine');
    }

    public function test_machine_catalog_can_be_searched(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');
        $token = $user->createToken('test-token')->plainTextToken;

        Machine::query()->create([
            'name' => 'Laser cutter',
            'slug' => 'laser-cutter',
            'manufacturer' => 'Acme',
            'model_number' => 'LC-900',
            'is_active' => true,
        ]);
        Machine::query()->create([
            'name' => 'Hydraulic press',
            'slug' => 'hydraulic-press',
            'manufacturer' => 'ForgeWorks',
            'model_number' => 'HP-200',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/machines?search=laser')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'laser-cutter');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/machines?search=hp-200')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'hydraulic-press');
    }

    public function test_admin_cannot_login_to_app_api(): void
    {
        $this->createUser('admin@example.com', 'admin');

        $this->postJson('/api/login', [
            'email' => 'admin@example.com',
            'password' => 'password',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_admin_token_cannot_access_app_api(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $token = $admin->createToken('test-admin-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/machines')
            ->assertForbidden();
    }

    public function test_guest_cannot_access_app_api(): void
    {
        $this->getJson('/api/machines')
            ->assertUnauthorized();
    }

    public function test_app_user_can_manage_personal_machine_list(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');
        $otherUser = $this->createUser('other@example.com', 'app_user');
        $machine = Machine::query()->create([
            'name' => 'Lathe A',
            'slug' => 'lathe-a',
            'is_active' => true,
        ]);
        $otherMachine = Machine::query()->create([
            'name' => 'Press B',
            'slug' => 'press-b',
            'is_active' => true,
        ]);

        $otherUser->machines()->attach($otherMachine);

        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/machines')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/user/machines/'.$machine->id)
            ->assertCreated()
            ->assertJsonPath('data.slug', 'lathe-a');

        $this->assertDatabaseHas('machine_user', [
            'machine_id' => $machine->id,
            'user_id' => $user->id,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/user/machines')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'lathe-a');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/user/machines/'.$machine->id)
            ->assertOk();

        $this->assertDatabaseMissing('machine_user', [
            'machine_id' => $machine->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_inactive_machine_cannot_be_added_to_personal_machine_list(): void
    {
        $user = $this->createUser('operator@example.com', 'app_user');
        $machine = Machine::query()->create([
            'name' => 'Inactive machine',
            'slug' => 'inactive-machine',
            'is_active' => false,
        ]);
        $token = $user->createToken('test-token')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/user/machines/'.$machine->id)
            ->assertNotFound();

        $this->assertDatabaseMissing('machine_user', [
            'machine_id' => $machine->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_only_admins_can_access_filament_panel(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $appUser = $this->createUser('operator@example.com', 'app_user');
        $panel = Filament::getPanel('admin');

        $this->assertTrue($admin->canAccessPanel($panel));
        $this->assertFalse($appUser->canAccessPanel($panel));
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
