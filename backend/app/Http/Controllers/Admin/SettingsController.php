<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\SettingsService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly AuditLogger $audit,
    ) {}

    public function index(): View
    {
        return view('admin.settings.index', [
            'definitions' => SettingsService::definitions(),
            'values' => $this->settings->all(),
            'environment' => self::environment(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [];
        foreach (SettingsService::definitions() as $key => $def) {
            $rules[$key] = match ($def['type']) {
                'bool' => ['required', 'boolean'],
                'int' => ['required', 'integer', 'min:'.($def['min'] ?? 0), 'max:'.($def['max'] ?? PHP_INT_MAX)],
                default => ['required', 'string', 'max:255'],
            };
        }
        $attributes = array_map(fn ($d) => strtolower($d['label']), SettingsService::definitions());
        $values = $request->validate($rules, [], $attributes);

        $changes = $this->settings->update($values, $request->user()->id);

        if ($changes === []) {
            return back()->with('status', 'No changes to save.');
        }

        $this->audit->log('settings.updated', actorId: $request->user()->id, resourceType: 'system_setting',
            metadata: ['changes' => $changes]);

        return back()->with('status', count($changes) === 1 ? 'Setting saved.' : count($changes).' settings saved.');
    }

    /**
     * Read-only runtime facts from config(). Never includes secrets.
     *
     * @return list<array{label: string, value: string, warn?: ?string}>
     */
    public static function environment(): array
    {
        $production = app()->environment('production');
        $debug = (bool) config('app.debug');
        $otpExposed = ! $production && (bool) config('otp.expose_in_response');
        $provider = (string) config('personnel.provider');

        return [
            ['label' => 'Environment (APP_ENV)', 'value' => (string) config('app.env')],
            ['label' => 'Debug mode (APP_DEBUG)', 'value' => $debug ? 'On' : 'Off',
                'warn' => $debug && $production ? 'Debug mode is on in production. Turn it off — it can expose sensitive details in error pages.' : null],
            ['label' => 'Application URL', 'value' => (string) config('app.url'),
                'warn' => $production && ! str_starts_with((string) config('app.url'), 'https://') ? 'The URL should use https in production.' : null],
            ['label' => 'Personnel provider', 'value' => $provider.(config('personnel.demo_accept_any') ? ' (accept any number)' : ''),
                'warn' => $provider === 'demo' ? 'Using demo personnel data. Connect the authorised NIS personnel source before go-live.' : null],
            ['label' => 'OTP driver', 'value' => (string) config('otp.driver')],
            ['label' => 'OTP codes in API responses', 'value' => $otpExposed ? 'Exposed' : 'Hidden',
                'warn' => $otpExposed ? 'Verification codes are returned in API responses. Only acceptable for local development and testing.' : null],
            ['label' => 'Push driver', 'value' => (string) config('services.push.driver')],
            ['label' => 'Broadcast connection', 'value' => (string) config('broadcasting.default')],
            ['label' => 'Queue connection', 'value' => (string) config('queue.default')],
            ['label' => 'Mail mailer', 'value' => (string) config('mail.default')],
            ['label' => 'Session lifetime', 'value' => (int) config('session.lifetime').' minutes'],
        ];
    }
}
