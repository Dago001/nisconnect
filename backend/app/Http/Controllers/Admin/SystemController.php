<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

/**
 * Read-only system health and go-live checklist. Never shows secrets.
 */
class SystemController extends Controller
{
    /** Cache key written by the scheduler heartbeat (see recordHeartbeat()). */
    public const HEARTBEAT_KEY = 'nis:scheduler:heartbeat';

    /** Disk used for private media uploads (see MediaController). */
    public const MEDIA_DISK = 'private';

    /**
     * Records that the scheduler is running. Register it once in routes/console.php:
     *   Schedule::call([\App\Http\Controllers\Admin\SystemController::class, 'recordHeartbeat'])
     *       ->everyMinute()->name('scheduler-heartbeat');
     */
    public static function recordHeartbeat(): void
    {
        Cache::forever(self::HEARTBEAT_KEY, Carbon::now()->toIso8601String());
    }

    public function index(): View
    {
        $checks = [
            $this->database(),
            $this->cache(),
            $this->queue(),
            ...$this->storage(),
            $this->broadcasting(),
            $this->scheduler(),
            $this->diskSpace(),
        ];

        $versions = [
            'Application' => config('app.name'),
            'Environment' => config('app.env'),
            'Laravel' => app()->version(),
            'PHP' => PHP_VERSION,
            'Server time' => Carbon::now()->format('d M Y, H:i:s T'),
            'Timezone' => config('app.timezone'),
        ];

        $checklist = $this->productionChecklist();

        return view('admin.system.index', compact('checks', 'versions', 'checklist'));
    }

    // --- Health checks -------------------------------------------------------

    /** @return array{name: string, status: string, detail: string} */
    private function database(): array
    {
        try {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'pgsql') {
                $version = (string) DB::selectOne('SHOW server_version')->server_version;
                $size = (int) DB::selectOne('SELECT pg_database_size(current_database()) AS s')->s;

                return $this->check('Database', 'ok', "Connected · PostgreSQL {$version} · ".$this->bytes($size));
            }
            DB::select('select 1');

            return $this->check('Database', 'warning', "Connected, but using {$driver}. NISconnect expects PostgreSQL.");
        } catch (Throwable $e) {
            return $this->check('Database', 'fail', 'Cannot connect to the database.');
        }
    }

    private function cache(): array
    {
        try {
            $key = 'nis:health:'.Str::random(12);
            $value = Str::random(16);
            Cache::put($key, $value, 30);
            $ok = Cache::get($key) === $value;
            Cache::forget($key);
            $store = (string) config('cache.default');

            if (! $ok) {
                return $this->check('Cache', 'fail', "Store “{$store}” did not return the value written.");
            }

            return $this->check('Cache', in_array($store, ['array', 'null'], true) ? 'warning' : 'ok',
                "Read/write OK · store “{$store}”".(in_array($store, ['array', 'null'], true) ? ' (not persistent)' : ''));
        } catch (Throwable) {
            return $this->check('Cache', 'fail', 'Cache read/write failed.');
        }
    }

    private function queue(): array
    {
        $connection = (string) config('queue.default');
        try {
            $parts = ["Connection “{$connection}”"];
            $status = $connection === 'sync' ? 'warning' : 'ok';
            if (Schema::hasTable('jobs')) {
                $pending = DB::table('jobs')->count();
                $parts[] = number_format($pending).' pending';
                if ($pending > 1000) {
                    $status = 'warning';
                }
            }
            if (Schema::hasTable('failed_jobs')) {
                $failed = DB::table('failed_jobs')->count();
                $parts[] = number_format($failed).' failed';
                if ($failed > 0) {
                    $status = 'warning';
                }
            }
            if ($connection === 'sync') {
                $parts[] = 'jobs run inline (no worker)';
            }

            return $this->check('Queue', $status, implode(' · ', $parts));
        } catch (Throwable) {
            return $this->check('Queue', 'fail', 'Could not read the queue tables.');
        }
    }

    /** @return list<array{name: string, status: string, detail: string}> */
    private function storage(): array
    {
        $out = [];
        foreach (array_unique(['local', self::MEDIA_DISK]) as $disk) {
            $label = $disk === self::MEDIA_DISK ? "Storage: “{$disk}” (media)" : "Storage: “{$disk}”";
            try {
                $path = '.health/'.Str::random(16).'.txt';
                $fs = Storage::disk($disk);
                $written = $fs->put($path, 'ok');
                $readable = $written && $fs->get($path) === 'ok';
                $fs->delete($path);
                $out[] = $readable
                    ? $this->check($label, 'ok', 'Writable · driver '.config("filesystems.disks.{$disk}.driver"))
                    : $this->check($label, 'fail', 'Not writable.');
            } catch (Throwable) {
                $out[] = $this->check($label, 'fail', 'Not writable or not configured.');
            }
        }

        return $out;
    }

    private function broadcasting(): array
    {
        $connection = (string) config('broadcasting.default');
        if ($connection === '' || $connection === 'null') {
            return $this->check('Realtime broadcasting', 'fail', 'Not configured — messages won’t arrive in real time.');
        }
        if ($connection === 'log') {
            return $this->check('Realtime broadcasting', 'warning', 'Using “log” — events are written to the log, not delivered.');
        }

        $configured = filled(config("broadcasting.connections.{$connection}.key"))
            || ! in_array(config("broadcasting.connections.{$connection}.driver"), ['reverb', 'pusher'], true);

        return $configured
            ? $this->check('Realtime broadcasting', 'ok', "Connection “{$connection}”")
            : $this->check('Realtime broadcasting', 'warning', "Connection “{$connection}” is missing its app key.");
    }

    private function scheduler(): array
    {
        $last = Cache::get(self::HEARTBEAT_KEY);
        if (! $last) {
            return $this->check('Scheduler', 'warning', 'No heartbeat seen. Make sure `php artisan schedule:run` runs every minute.');
        }
        $at = Carbon::parse($last);
        $status = $at->lt(Carbon::now()->subMinutes(5)) ? 'warning' : 'ok';

        return $this->check('Scheduler', $status, 'Last heartbeat '.$at->diffForHumans());
    }

    private function diskSpace(): array
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());
        if ($free === false || $total === false || $total <= 0) {
            return $this->check('Disk space', 'warning', 'Could not read free disk space.');
        }
        $pct = $free / $total * 100;
        $status = $pct < 5 ? 'fail' : ($pct < 15 ? 'warning' : 'ok');

        return $this->check('Disk space', $status, $this->bytes((int) $free).' free of '.$this->bytes((int) $total).' ('.round($pct).'%)');
    }

    // --- Go-live checklist ---------------------------------------------------

    /** @return list<array{label: string, pass: bool, help: string}> */
    private function productionChecklist(): array
    {
        $mailer = (string) config('mail.default');
        $superAdmins2fa = User::query()
            ->where('account_state', User::STATE_ACTIVE)
            ->whereNotNull('two_factor_secret')->whereNotNull('two_factor_confirmed_at')
            ->whereHas('roles.role', fn ($q) => $q->where('name', 'super_admin'))
            ->count();

        return [
            ['label' => 'Debug mode is off', 'pass' => ! config('app.debug'),
                'help' => 'Set APP_DEBUG=false.'],
            ['label' => 'Application URL uses https', 'pass' => str_starts_with((string) config('app.url'), 'https://'),
                'help' => 'Set APP_URL to the https address of this server.'],
            ['label' => 'Real personnel source connected', 'pass' => config('personnel.provider') !== 'demo',
                'help' => 'Set PERSONNEL_PROVIDER to the authorised NIS source (api or database).'],
            ['label' => '“Accept any Service Number” is off', 'pass' => ! config('personnel.demo_accept_any'),
                'help' => 'Set PERSONNEL_DEMO_ACCEPT_ANY=false.'],
            ['label' => 'OTP codes are not returned in API responses', 'pass' => ! config('otp.expose_in_response'),
                'help' => 'Set OTP_EXPOSE_IN_RESPONSE=false.'],
            ['label' => 'Real SMS driver for OTPs', 'pass' => config('otp.driver') !== 'log',
                'help' => 'Set OTP_DRIVER=sms and the SMS provider settings.'],
            ['label' => 'Mail is configured', 'pass' => ! in_array($mailer, ['', 'log', 'array'], true),
                'help' => 'Set MAIL_MAILER (e.g. smtp) and its settings.'],
            ['label' => 'At least one Super Administrator uses two-factor authentication', 'pass' => $superAdmins2fa > 0,
                'help' => 'A Super Administrator should set it up under My account → Two-factor authentication.'],
        ];
    }

    // --- Helpers -------------------------------------------------------------

    /** @return array{name: string, status: string, detail: string} */
    private function check(string $name, string $status, string $detail): array
    {
        return compact('name', 'status', 'detail');
    }

    private function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, $i ? 1 : 0).' '.$units[$i];
    }
}
