<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * The personnel page tells an administrator what actually governs registration.
 * Getting this wrong is costly in both directions: it either hides that sign-up
 * is open to anyone, or implies the onboarded list is ignored when it is not.
 */
class PersonnelProviderNoticeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::create([
            'service_number' => '900900',
            'display_name' => 'Portal Admin',
            'account_state' => User::STATE_ACTIVE,
            'privacy' => User::defaultPrivacy(),
        ]);
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::where('name', 'super_admin')->value('id'),
        ]);

        return $user->fresh();
    }

    #[Test]
    public function it_warns_loudly_when_the_demo_source_accepts_anyone(): void
    {
        config(['personnel.provider' => 'demo', 'personnel.demo_accept_any' => true]);

        $this->actingAs($this->admin())
            ->get(route('admin.personnel.index'))
            ->assertOk()
            ->assertSee('Anyone can register right now.');
    }

    #[Test]
    public function it_says_the_list_controls_registration_under_the_roster_provider(): void
    {
        config(['personnel.provider' => 'roster']);

        $this->actingAs($this->admin())
            ->get(route('admin.personnel.index'))
            ->assertOk()
            ->assertSee('This list controls registration.')
            ->assertDontSee('Anyone can register right now.');
    }

    #[Test]
    public function the_demo_source_without_accept_any_is_not_an_open_door(): void
    {
        // Only the fixed demo Service Numbers work, so it is a note, not an alarm.
        config(['personnel.provider' => 'demo', 'personnel.demo_accept_any' => false]);

        $this->actingAs($this->admin())
            ->get(route('admin.personnel.index'))
            ->assertOk()
            ->assertDontSee('Anyone can register right now.');
    }
}
