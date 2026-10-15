<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MakeAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
    }

    #[Test]
    public function it_creates_an_administrator_who_can_reach_the_portal(): void
    {
        $this->artisan('nisconnect:make-admin', ['service-number' => '900100'])
            ->assertSuccessful();

        $user = User::where('service_number', '900100')->firstOrFail();

        $this->assertTrue($user->canAccessAdminPortal());
        $this->assertNotEmpty($user->password_hash);
        // Chosen out of band, so it must be replaced at first sign-in.
        $this->assertTrue($user->must_change_password);
    }

    #[Test]
    public function it_preserves_a_leading_zero_in_the_service_number(): void
    {
        $this->artisan('nisconnect:make-admin', ['service-number' => '000123'])
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['service_number' => '000123']);
        $this->assertDatabaseMissing('users', ['service_number' => '123']);
    }

    #[Test]
    public function rerunning_updates_rather_than_duplicating(): void
    {
        $this->artisan('nisconnect:make-admin', ['service-number' => '900101'])->assertSuccessful();
        $first = User::where('service_number', '900101')->firstOrFail()->password_hash;

        $this->artisan('nisconnect:make-admin', [
            'service-number' => '900101',
            '--password' => 'An0ther!Passw0rd#',
        ])->assertSuccessful();

        $this->assertSame(1, User::where('service_number', '900101')->count());
        $this->assertNotSame($first, User::where('service_number', '900101')->firstOrFail()->password_hash);
    }

    #[Test]
    public function it_refuses_a_weak_password(): void
    {
        $this->artisan('nisconnect:make-admin', [
            'service-number' => '900102',
            '--password' => 'short',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['service_number' => '900102']);
    }

    #[Test]
    public function it_refuses_a_non_numeric_service_number(): void
    {
        $this->artisan('nisconnect:make-admin', ['service-number' => 'ABC123'])
            ->assertFailed();
    }

    #[Test]
    public function it_refuses_an_unknown_role(): void
    {
        $this->artisan('nisconnect:make-admin', [
            'service-number' => '900103',
            '--role' => 'not_a_role',
        ])->assertFailed();

        $this->assertDatabaseMissing('users', ['service_number' => '900103']);
    }

    #[Test]
    public function it_can_grant_a_narrower_role(): void
    {
        $this->artisan('nisconnect:make-admin', [
            'service-number' => '900104',
            '--role' => 'security_admin',
        ])->assertSuccessful();

        $user = User::where('service_number', '900104')->firstOrFail();
        $roleId = Role::where('name', 'security_admin')->value('id');

        $this->assertDatabaseHas('user_roles', ['user_id' => $user->id, 'role_id' => $roleId]);
    }
}
