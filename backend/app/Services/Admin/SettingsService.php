<?php

namespace App\Services\Admin;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Runtime system settings, editable by super administrators in the portal.
 * Every setting listed here is enforced somewhere in the code.
 */
class SettingsService
{
    private const CACHE_KEY = 'system_settings:v1';

    /**
     * key => [type, default, label, help]
     *
     * @return array<string, array{type: string, default: mixed, label: string, help: string, min?: int, max?: int}>
     */
    public static function definitions(): array
    {
        return [
            'registration_open' => [
                'type' => 'bool', 'default' => true,
                'label' => 'Allow new officer registration',
                'help' => 'When off, the app refuses new sign-ups. Existing officers can still sign in.',
            ],
            'admin_require_2fa' => [
                'type' => 'bool', 'default' => false,
                'label' => 'Require two-factor authentication for administrators',
                'help' => 'Administrators without an authenticator app are sent to set one up before they can continue.',
            ],
            'admin_session_timeout' => [
                'type' => 'int', 'default' => 30, 'min' => 5, 'max' => 480,
                'label' => 'Admin idle timeout (minutes)',
                'help' => 'Administrators are signed out after this long without activity.',
            ],
            'admin_password_min_length' => [
                'type' => 'int', 'default' => 12, 'min' => 10, 'max' => 64,
                'label' => 'Minimum administrator password length',
                'help' => 'Applies when an administrator sets or changes their password.',
            ],
            'admin_password_max_age_days' => [
                'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 365,
                'label' => 'Administrator password expiry (days)',
                'help' => '0 = never. Administrators must choose a new password once it is older than this.',
            ],
            'max_devices_per_officer' => [
                'type' => 'int', 'default' => 5, 'min' => 1, 'max' => 20,
                'label' => 'Maximum signed-in devices per officer',
                'help' => 'Signing in on another device beyond this limit signs out the least recently used one.',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        $stored = Cache::remember(self::CACHE_KEY, 300, function () {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::pluck('value', 'key')->all();
        });

        $out = [];
        foreach (self::definitions() as $key => $def) {
            $out[$key] = array_key_exists($key, $stored) ? $this->cast($def, $stored[$key]) : $def['default'];
        }

        return $out;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key] ?? (self::definitions()[$key]['default'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, array{from: mixed, to: mixed}> the changes made
     */
    public function update(array $values, ?string $actorId): array
    {
        $current = $this->all();
        $changes = [];
        foreach (self::definitions() as $key => $def) {
            if (! array_key_exists($key, $values)) {
                continue;
            }
            $new = $this->cast($def, $values[$key]);
            if ($new !== $current[$key]) {
                SystemSetting::updateOrCreate(['key' => $key], ['value' => $new, 'updated_by' => $actorId]);
                $changes[$key] = ['from' => $current[$key], 'to' => $new];
            }
        }
        Cache::forget(self::CACHE_KEY);

        return $changes;
    }

    private function cast(array $def, mixed $value): mixed
    {
        return match ($def['type']) {
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int' => max($def['min'] ?? PHP_INT_MIN, min($def['max'] ?? PHP_INT_MAX, (int) $value)),
            default => (string) $value,
        };
    }
}
