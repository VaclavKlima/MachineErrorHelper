<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_render_user_management_pages(): void
    {
        $admin = $this->createUser('admin@example.com', 'admin');
        $appUser = $this->createUser('operator@example.com', 'app_user');

        Permission::findOrCreate('manage users', 'web');
        $appUser->givePermissionTo('manage users');

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/users/create')
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/users/'.$appUser->id)
            ->assertOk();

        $this->actingAs($admin)
            ->get('/admin/users/'.$appUser->id.'/edit')
            ->assertOk();
    }

    private function createUser(string $email, string $role): User
    {
        Role::findOrCreate($role, 'web');

        $user = User::query()->create([
            'name' => $email,
            'email' => $email,
            'password' => Hash::make('password'),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
