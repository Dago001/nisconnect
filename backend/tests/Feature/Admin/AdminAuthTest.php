<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\SettingsService;
use App\Services\Admin\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;

class AdminAuthTest extends AdminTestCase
{
    public function test_admin_signs_in_and_sees_dashboard(): void
    {
        $admin = $this->makeAdmin();

        $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.dashboard'));
        $this->get(route('admin.dashboard'))->assertOk()->assertSee('Dashboard');
        $this->assertDatabaseHas('audit_logs', ['action' => 'admin.login.success', 'actor_id' => $admin->id]);
    }

    public function test_wrong_password_and_non_admin_are_refused(): void
    {
        $admin = $this->makeAdmin();
        $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => 'nope'])
            ->assertSessionHasErrors('service_number');
        $this->assertGuest();

        $officer = $this->makeAdmin('officer');
        $this->post(route('admin.login.submit'), ['service_number' => $officer->service_number, 'password' => self::PASSWORD])
            ->assertSessionHasErrors('service_number');
        $this->assertGuest();
    }

    public function test_login_is_rate_limited(): void
    {
        $admin = $this->makeAdmin();
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => 'bad']);
        }
        $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => self::PASSWORD])
            ->assertStatus(429);
    }

    public function test_two_factor_setup_and_challenge(): void
    {
        $admin = $this->makeAdmin();
        $this->asAdmin($admin)->post(route('admin.account.two-factor.enable'))->assertRedirect();
        $secret = session('admin_2fa_setup_secret');
        $this->assertNotEmpty($secret);

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->post(route('admin.account.two-factor.confirm'), ['code' => $code])->assertRedirect();
        $this->assertTrue($admin->fresh()->hasTwoFactorEnabled());

        // Next sign-in requires the code.
        $this->post(route('admin.logout'));
        $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => self::PASSWORD])
            ->assertRedirect(route('admin.two-factor.challenge'));
        $this->assertGuest();
        $this->post(route('admin.two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post(route('admin.two-factor.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
            ->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin->fresh());
    }

    public function test_recovery_code_works_once(): void
    {
        $admin = $this->makeAdmin();
        $svc = app(TwoFactorService::class);
        [$plain, $hashes] = $svc->makeRecoveryCodes(2);
        $admin->forceFill(['two_factor_secret' => $svc->generateSecret(), 'two_factor_recovery_codes' => $hashes,
            'two_factor_confirmed_at' => now()])->save();

        $this->post(route('admin.login.submit'), ['service_number' => $admin->service_number, 'password' => self::PASSWORD]);
        $this->post(route('admin.two-factor.verify'), ['recovery_code' => $plain[0]])->assertRedirect(route('admin.dashboard'));
        $this->assertCount(1, $admin->fresh()->two_factor_recovery_codes);
    }

    public function test_idle_session_expires(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin)->withSession(['admin_last_activity' => time() - 3600])
            ->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->assertGuest();
    }

    public function test_temporary_password_must_be_changed_first(): void
    {
        $admin = $this->makeAdmin();
        $admin->forceFill(['must_change_password' => true])->save();

        $this->asAdmin($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.account.password'));
        $this->put(route('admin.account.password.update'), [
            'current_password' => self::PASSWORD,
            'password' => 'Brand-New-Passw0rd!', 'password_confirmation' => 'Brand-New-Passw0rd!',
        ])->assertRedirect(route('admin.account'));
        $this->assertFalse($admin->fresh()->must_change_password);
        $this->get(route('admin.dashboard'))->assertOk();
    }

    public function test_weak_new_password_is_rejected(): void
    {
        $this->asAdmin()->put(route('admin.account.password.update'), [
            'current_password' => self::PASSWORD, 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertSessionHasErrors('password');
    }

    public function test_required_two_factor_policy_redirects_to_setup(): void
    {
        app(SettingsService::class)->update(['admin_require_2fa' => true], null);
        $this->asAdmin()->get(route('admin.dashboard'))->assertRedirect(route('admin.account.two-factor'));
    }

    public function test_permissions_gate_each_area(): void
    {
        $security = $this->makeAdmin('security_admin');
        $this->asAdmin($security)->get(route('admin.dashboard'))->assertOk();
        $this->get(route('admin.settings.index'))->assertForbidden();
        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_guest_is_sent_to_login_and_headers_are_strict(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $res = $this->get(route('admin.login'))->assertOk();
        $this->assertStringContainsString("frame-ancestors 'none'", $res->headers->get('Content-Security-Policy'));
        $this->assertSame('DENY', $res->headers->get('X-Frame-Options'));
    }

    public function test_create_admin_command_bootstraps_a_super_admin(): void
    {
        $this->artisan('nis:create-admin', [
            'service_number' => '000777', '--name' => 'Ada Okafor', '--password' => 'Strong-Passw0rd!x',
        ])->assertSuccessful();

        $user = User::where('service_number', '000777')->firstOrFail();
        $this->assertTrue($user->isSuperAdmin());
        $this->post(route('admin.login.submit'), ['service_number' => '000777', 'password' => 'Strong-Passw0rd!x'])
            ->assertRedirect(route('admin.dashboard'));
    }
}
