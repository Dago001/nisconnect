<?php

use App\Http\Controllers\Admin\SystemController;
use App\Models\PersonnelRecord;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\Rules\AdminPassword;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/*
 * Creates (or promotes) an administrator from the command line. This is how
 * the first Super Administrator is created on a new server:
 *
 *   php artisan nis:create-admin 123456
 *
 * Hosting platforms without a shell (the Render blueprint) call it at start-up
 * with --from-env: the Service Number, name and password come from
 * NIS_ADMIN_SERVICE_NUMBER / NIS_ADMIN_NAME / NIS_ADMIN_PASSWORD, nothing
 * happens once that account has a password (so restarts never reset it), and
 * the password must be changed at first sign-in.
 */
Artisan::command('nis:create-admin {service_number?} {--role=super_admin} {--name=} {--password=} {--from-env}', function () {
    $fromEnv = (bool) $this->option('from-env');
    $sn = (string) ($fromEnv ? env('NIS_ADMIN_SERVICE_NUMBER', '') : $this->argument('service_number'));
    if ($fromEnv) {
        if ($sn === '' || (string) env('NIS_ADMIN_PASSWORD', '') === '') {
            $this->line('NIS_ADMIN_SERVICE_NUMBER / NIS_ADMIN_PASSWORD not set; no administrator created.');

            return 0;
        }
        if (User::where('service_number', $sn)->whereNotNull('password_hash')->exists()) {
            $this->line("Administrator {$sn} already exists; leaving it unchanged.");

            return 0;
        }
        $this->input->setOption('name', (string) env('NIS_ADMIN_NAME', '') ?: 'Super Administrator');
        $this->input->setOption('password', (string) env('NIS_ADMIN_PASSWORD'));
    }
    if (! preg_match('/^[0-9]{1,20}$/', $sn)) {
        $this->error('Service Numbers contain digits only.');

        return 1;
    }
    $role = Role::where('name', $this->option('role'))->first();
    if (! $role) {
        $this->error('Unknown role. Run `php artisan db:seed --force` first, or choose one of: '.Role::pluck('name')->implode(', '));

        return 1;
    }

    $user = User::where('service_number', $sn)->first();
    if (! $user) {
        $name = $this->option('name') ?: text('Full name for this administrator', required: true);
        $parts = preg_split('/\s+/', trim($name));
        $record = PersonnelRecord::firstOrCreate(['service_number' => $sn], [
            'surname' => array_pop($parts) ?: $name,
            'first_name' => implode(' ', $parts) ?: $name,
            'status' => 'active',
            'source' => 'admin',
        ]);
        $user = User::create([
            'personnel_record_id' => $record->id,
            'service_number' => $sn,
            'display_name' => $name,
            'account_state' => User::STATE_ACTIVE,
            'privacy' => User::defaultPrivacy(),
        ]);
        UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => Role::where('name', 'officer')->value('id')]);
        $this->info("Created account for {$name} ({$sn}).");
    }

    $plain = $this->option('password') ?: password('Administrator password', required: true, hint: 'Min. length with upper/lower case, a number and a symbol');
    $check = Validator::make(['password' => $plain], ['password' => [AdminPassword::rule()]]);
    if ($check->fails()) {
        $this->error($check->errors()->first('password'));

        return 1;
    }

    $user->forceFill([
        'password_hash' => Hash::make($plain),
        'password_changed_at' => now(),
        'must_change_password' => $fromEnv,
        'account_state' => User::STATE_ACTIVE,
    ])->save();
    UserRole::firstOrCreate(['user_id' => $user->id, 'role_id' => $role->id, 'scope_type' => null, 'scope_id' => null]);

    $this->info("{$user->display_name} ({$sn}) is now {$role->label}. Sign in at ".url('/admin/login'));

    return 0;
})->purpose('Create or promote an administrator (use for the first Super Administrator)');

// Housekeeping
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
// Lets the System health page confirm the scheduler is running.
Schedule::call([SystemController::class, 'recordHeartbeat'])->everyMinute()->name('scheduler-heartbeat');
