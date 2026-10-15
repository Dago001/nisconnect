<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Services\Support\AuditLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * Creates the first administrator on a fresh deployment.
 *
 * A new environment has roles (from the seeder) but no one who can sign in to
 * the portal, and the portal is the only way to grant a role — so without this
 * the first admin has to be made by hand in tinker, which is easy to get
 * subtly wrong (a user with no role, or an unhashed password).
 *
 * Safe to re-run: it updates the password of an existing Service Number rather
 * than creating a duplicate, which also makes it the recovery path when
 * someone is locked out.
 */
class MakeAdminCommand extends Command
{
    protected $signature = 'nisconnect:make-admin
                            {service-number : The officer\'s Service Number (digits, leading zeroes preserved)}
                            {--name= : Display name (defaults to "Administrator")}
                            {--role=super_admin : Role to grant}
                            {--password= : Password; omit to have one generated}';

    protected $description = 'Create or update an administrator who can sign in to the admin portal';

    public function handle(AuditLogger $audit): int
    {
        $serviceNumber = (string) $this->argument('service-number');
        $roleName = (string) $this->option('role');

        if (! preg_match('/^\d+$/', $serviceNumber)) {
            $this->error('Service Number must be digits only (leading zeroes are kept).');

            return self::FAILURE;
        }

        $role = Role::where('name', $roleName)->first();
        if (! $role) {
            $this->error("Role \"{$roleName}\" does not exist. Run: php artisan db:seed");
            $this->line('Available: '.Role::pluck('name')->implode(', '));

            return self::FAILURE;
        }

        // A generated password is 24 random characters; nothing weaker is
        // acceptable for an account that can suspend officers and revoke devices.
        $password = (string) ($this->option('password') ?: Str::password(24));

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', Password::min(12)->letters()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            $this->error('Password too weak: '.$validator->errors()->first('password'));

            return self::FAILURE;
        }

        $existing = User::where('service_number', $serviceNumber)->exists();

        $user = DB::transaction(function () use ($serviceNumber, $password, $role) {
            $user = User::firstOrNew(['service_number' => $serviceNumber]);
            $user->display_name = (string) ($this->option('name') ?: $user->display_name ?: 'Administrator');
            $user->account_state = User::STATE_ACTIVE;
            $user->password_hash = Hash::make($password);
            $user->password_changed_at = now();
            // The operator chose this password out of band, so make them replace
            // it at first sign-in.
            $user->must_change_password = true;
            $user->save();

            UserRole::firstOrCreate(
                ['user_id' => $user->id, 'role_id' => $role->id],
                ['scope_type' => null, 'scope_id' => null],
            );

            return $user;
        });

        $user->refresh();
        $user->flushPermissionCache();

        $audit->log($existing ? 'admin.account.updated' : 'admin.account.created', actorId: $user->id);

        $this->newLine();
        $this->info($existing ? 'Administrator updated.' : 'Administrator created.');
        $this->table(['Field', 'Value'], [
            ['Service Number', $serviceNumber],
            ['Name', $user->display_name],
            ['Role', $roleName],
            ['Password', $password],
            ['Portal access', $user->canAccessAdminPortal() ? 'yes' : 'NO — check the role grants dashboard.view'],
        ]);
        $this->warn('Copy the password now; it is not stored anywhere and cannot be shown again.');
        $this->line('Sign in at /admin/login — you will be asked to set a new password.');

        return self::SUCCESS;
    }
}
