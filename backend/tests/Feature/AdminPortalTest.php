<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['password_hash' => Hash::make('portal-pass')])->save();
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::where('name', 'super_admin')->first()->id,
        ]);

        return $user;
    }

    public function test_admin_can_log_in_and_view_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $this->post(route('admin.login.submit'), [
            'service_number' => $admin->service_number,
            'password' => 'portal-pass',
        ])->assertRedirect(route('admin.dashboard'));

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()->assertSee('Dashboard');
    }

    public function test_non_admin_officer_is_denied_the_portal(): void
    {
        $officer = User::factory()->create();
        $officer->forceFill(['password_hash' => Hash::make('pw12345678')])->save();

        $this->post(route('admin.login.submit'), [
            'service_number' => $officer->service_number,
            'password' => 'pw12345678',
        ])->assertSessionHasErrors('service_number');
    }

    public function test_guest_cannot_reach_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_suspend_and_reactivate_an_account(): void
    {
        $admin = $this->makeAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)->post(route('admin.users.suspend', $target))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'account_state' => User::STATE_SUSPENDED]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.user.suspended']);

        $this->actingAs($admin)->post(route('admin.users.reactivate', $target))->assertRedirect();
        $this->assertDatabaseHas('users', ['id' => $target->id, 'account_state' => User::STATE_ACTIVE]);
    }
}
