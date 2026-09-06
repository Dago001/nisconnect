<?php

namespace App\Providers;

use App\Personnel\PersonnelProviderInterface;
use App\Personnel\Providers\ApiPersonnelProvider;
use App\Personnel\Providers\DatabasePersonnelProvider;
use App\Personnel\Providers\DemoPersonnelProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

/**
 * Binds the configured personnel provider. Swapping the provider is a config
 * change (PERSONNEL_PROVIDER) — no controller or service code changes.
 */
class PersonnelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PersonnelProviderInterface::class, function (Application $app) {
            $driver = (string) config('personnel.provider', 'demo');

            return match ($driver) {
                'demo' => new DemoPersonnelProvider($app->environment()),
                'api' => new ApiPersonnelProvider(
                    $app->make(HttpFactory::class),
                    (array) config('personnel.api'),
                ),
                'database' => new DatabasePersonnelProvider(
                    $app->make(DatabaseManager::class),
                    (array) config('personnel.database'),
                ),
                default => throw new InvalidArgumentException(
                    "Unknown personnel provider [{$driver}]."
                ),
            };
        });
    }
}
