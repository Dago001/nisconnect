<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Shared helpers for admin portal tests. */
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    public const PASSWORD = 'Correct-Horse-9-Battery';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    protected function makeAdmin(string $role = 'super_admin', array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->forceFill(['password_hash' => Hash::make(self::PASSWORD), 'password_changed_at' => now()])->save();
        UserRole::create(['user_id' => $user->id, 'role_id' => Role::where('name', $role)->value('id')]);

        return $user->fresh();
    }

    /** Acts as an admin with a live portal session. */
    protected function asAdmin(?User $admin = null): static
    {
        $admin ??= $this->makeAdmin();

        return $this->actingAs($admin)->withSession(['admin_last_activity' => time()]);
    }
}
