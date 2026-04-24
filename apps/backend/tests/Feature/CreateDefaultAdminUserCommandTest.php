<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CreateDefaultAdminUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_the_default_admin_user(): void
    {
        $this->artisan('admin:create-default-user')
            ->expectsOutputToContain('Created admin user')
            ->expectsOutputToContain('Email: admin@example.com')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Admin', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('app_user'));
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_promotes_an_existing_user_to_admin(): void
    {
        Role::findOrCreate('app_user', 'web');

        $user = User::query()->create([
            'name' => 'Operator',
            'email' => 'operator@example.com',
            'password' => 'old-password',
        ]);

        $user->assignRole('app_user');

        $this->artisan('admin:create-default-user', [
            '--name' => 'Operations Admin',
            '--email' => 'operator@example.com',
            '--password' => 'new-password',
        ])
            ->expectsOutputToContain('Updated admin user')
            ->expectsOutputToContain('Email: operator@example.com')
            ->assertExitCode(0);

        $user->refresh();

        $this->assertSame('Operations Admin', $user->name);
        $this->assertTrue(Hash::check('new-password', $user->password));
        $this->assertTrue($user->hasRole('admin'));
        $this->assertFalse($user->hasRole('app_user'));
        $this->assertNotNull($user->email_verified_at);
    }
}
