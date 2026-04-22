<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate('admin', 'web');
        Role::findOrCreate('app_user', 'web');

        User::query()
            ->doesntHave('roles')
            ->each(fn (User $user): User => $user->assignRole('admin'));
    }

    public function down(): void
    {
        Role::query()
            ->whereIn('name', ['admin', 'app_user'])
            ->where('guard_name', 'web')
            ->delete();
    }
};
