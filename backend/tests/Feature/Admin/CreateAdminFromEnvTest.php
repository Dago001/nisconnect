<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminFromEnvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    protected function tearDown(): void
    {
        foreach (['NIS_ADMIN_SERVICE_NUMBER', 'NIS_ADMIN_NAME', 'NIS_ADMIN_PASSWORD'] as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
        parent::tearDown();
    }

    private function env(array $vars): void
    {
        foreach ($vars as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $_SERVER[$k] = $v;
        }
    }

    public function test_does_nothing_without_env(): void
    {
        $this->artisan('nis:create-admin --from-env')->assertExitCode(0);
        $this->assertSame(0, User::whereNotNull('password_hash')->count());
    }

    public function test_creates_super_admin_once_and_forces_password_change(): void
    {
        $this->env(['NIS_ADMIN_SERVICE_NUMBER' => '100001', 'NIS_ADMIN_NAME' => 'Ada Bello', 'NIS_ADMIN_PASSWORD' => 'Correct-Horse-9-Battery']);
        $this->artisan('nis:create-admin --from-env')->assertExitCode(0);

        $user = User::where('service_number', '100001')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue((bool) $user->must_change_password);
        $this->assertSame('Ada Bello', $user->display_name);

        // A restart with a different password must not reset the account.
        $this->env(['NIS_ADMIN_PASSWORD' => 'Another-Pass-7-Word!']);
        $this->artisan('nis:create-admin --from-env')->assertExitCode(0);
        $this->assertTrue(Hash::check('Correct-Horse-9-Battery', $user->fresh()->password_hash));
    }

    public function test_rejects_weak_password(): void
    {
        $this->env(['NIS_ADMIN_SERVICE_NUMBER' => '100002', 'NIS_ADMIN_PASSWORD' => 'weak']);
        $this->artisan('nis:create-admin --from-env')->assertExitCode(1);
    }
}
