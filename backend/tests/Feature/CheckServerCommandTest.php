<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckServerCommandTest extends TestCase
{
    use RefreshDatabase;

    /** The last test points the default connection elsewhere; keep the rollback on the real one. */
    protected $connectionsToTransact = ['pgsql'];

    public function test_a_correctly_configured_server_passes(): void
    {
        config(['app.debug' => false, 'personnel.provider' => 'roster']);

        $this->artisan('nisconnect:check-server')
            ->expectsOutputToContain('Database connection (pgsql)')
            ->expectsOutputToContain('Database tables are up to date')
            ->expectsOutputToContain('PHP extension pdo_pgsql')
            ->expectsOutputToContain('This server can run NISconnect.')
            ->assertSuccessful();
    }

    public function test_it_fails_with_the_fix_when_something_is_wrong(): void
    {
        config(['app.debug' => true, 'personnel.provider' => 'demo']);

        $this->artisan('nisconnect:check-server')
            ->expectsOutputToContain('Set APP_DEBUG=false')
            ->expectsOutputToContain('Set PERSONNEL_PROVIDER=roster')
            ->expectsOutputToContain('2 problem(s) to fix')
            ->assertFailed();
    }

    public function test_it_reports_a_database_it_cannot_reach_without_printing_the_password(): void
    {
        config([
            'app.debug' => false,
            'personnel.provider' => 'roster',
            'database.connections.unreachable' => array_merge(config('database.connections.pgsql'), [
                'port' => 1,
                'password' => 'never-print-this',
            ]),
            'database.default' => 'unreachable',
        ]);

        $this->artisan('nisconnect:check-server')
            ->expectsOutputToContain('Cannot connect. Check DB_HOST')
            ->doesntExpectOutputToContain('never-print-this')
            ->assertFailed();
    }
}
