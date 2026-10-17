<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Checks that a server can run NISconnect, for hosting where nobody controls
 * the PHP build (shared hosting such as a Nairahost/DirectAdmin account).
 *
 *   php artisan nisconnect:check-server
 *
 * Every line is OK, WARN or FAIL; it exits non-zero when anything FAILs, so a
 * missing PHP extension or a wrong database password shows up here instead of
 * as a blank page in the browser. It changes nothing and prints no secrets.
 */
class CheckServerCommand extends Command
{
    protected $signature = 'nisconnect:check-server';

    protected $description = 'Check that this server has what NISconnect needs (PHP, extensions, database, folders)';

    /** What the code and its dependencies use (composer check-platform-reqs) (the database driver is checked separately). */
    public const EXTENSIONS = ['openssl', 'mbstring', 'fileinfo', 'curl', 'dom', 'tokenizer', 'iconv'];

    private int $failures = 0;

    public function handle(Migrator $migrator): int
    {
        $this->report(version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP '.PHP_VERSION, 'NISconnect needs PHP 8.2 or newer; choose it in the hosting panel\'s PHP selector.');

        foreach (self::EXTENSIONS as $ext) {
            $this->report(extension_loaded($ext), "PHP extension {$ext}", "Turn on \"{$ext}\" in the hosting panel's PHP extensions list.");
        }

        try {
            $ok = Hash::check('nisconnect-check', Hash::make('nisconnect-check'));
        } catch (Throwable) {
            $ok = false;
        }
        $this->report($ok, 'Password hashing', 'This PHP build cannot hash passwords with the configured algorithm.');

        $this->report(filled(config('app.key')), 'APP_KEY is set', 'Run: php artisan key:generate --force');
        $this->report(! config('app.debug'), 'APP_DEBUG is off', 'Set APP_DEBUG=false in .env; debug pages reveal settings.');
        $url = (string) config('app.url');
        $this->report(str_starts_with($url, 'https://'), "APP_URL {$url}", 'Set APP_URL to the https:// address in .env.', warnOnly: true);

        $provider = (string) config('personnel.provider');
        $this->report($provider !== 'demo', "Personnel source: {$provider}", 'The demo source lets anyone register. Set PERSONNEL_PROVIDER=roster in .env.');

        $pdo = ['pgsql' => 'pdo_pgsql', 'mysql' => 'pdo_mysql', 'mariadb' => 'pdo_mysql'][config('database.connections.'.config('database.default').'.driver')] ?? null;
        if ($pdo) {
            $this->report(extension_loaded($pdo), "PHP extension {$pdo}", "Turn on \"{$pdo}\" in the hosting panel's PHP extensions list.");
        }

        try {
            $driver = DB::connection()->getDriverName();
            DB::connection()->getPdo();
            $this->report(in_array($driver, ['pgsql', 'mysql', 'mariadb'], true), "Database connection ({$driver})", 'NISconnect needs PostgreSQL or MySQL/MariaDB: set DB_CONNECTION in .env.');

            $migrator->setConnection(config('database.default'));
            if ($migrator->repositoryExists()) {
                $pending = array_diff(
                    array_keys($migrator->getMigrationFiles(array_merge([database_path('migrations')], $migrator->paths()))),
                    $migrator->getRepository()->getRan(),
                );
                $this->report($pending === [], 'Database tables are up to date', count($pending).' update(s) pending. Run: php artisan migrate --force');
            } else {
                $this->report(false, 'Database tables', 'The database is empty. Run: php artisan migrate --force --seed');
            }
        } catch (Throwable $e) {
            $this->report(false, 'Database connection', 'Cannot connect. Check DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env ('.class_basename($e).').');
        }

        foreach ([storage_path('framework'), storage_path('logs'), storage_path('app'), base_path('bootstrap/cache')] as $dir) {
            $this->report(is_dir($dir) && is_writable($dir), 'Writable: '.str_replace(base_path().'/', '', $dir), "PHP must be able to write here. Run: chmod -R u+rwX {$dir}");
        }

        $this->report(is_file(public_path('index.html')), 'Officer web app files', 'public/index.html is missing: this copy has no web app. Use the server3 package, which includes it.', warnOnly: true);

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("{$this->failures} problem(s) to fix before NISconnect can run here.");

            return self::FAILURE;
        }
        $this->info('This server can run NISconnect.');

        return self::SUCCESS;
    }

    private function report(bool $ok, string $label, string $fix, bool $warnOnly = false): void
    {
        if ($ok) {
            $this->line("  <info>OK</info>    {$label}");

            return;
        }
        if ($warnOnly) {
            $this->line("  <comment>WARN</comment>  {$label} — {$fix}");

            return;
        }
        $this->failures++;
        $this->line("  <error>FAIL</error>  {$label} — {$fix}");
    }
}
