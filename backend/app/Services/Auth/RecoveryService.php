<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\OtpVerification;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Secure account recovery. Never recovers by Service Number alone — it always
 * requires possession of the registered phone (OTP). All steps are audited and
 * a security event is raised. Responses never reveal whether an account exists.
 */
class RecoveryService
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
    ) {}

    private const PREFIX = 'recovery:';

    /**
     * Step 1 — start recovery. Always returns a recovery_id + generic message;
     * an OTP is sent only when an active account with a phone actually exists.
     *
     * @return array{recovery_id: string, code: ?string}
     */
    public function start(string $serviceNumber): array
    {
        $recoveryId = (string) Str::uuid();
        $user = User::where('service_number', $serviceNumber)
            ->where('account_state', User::STATE_ACTIVE)
            ->first();

        $code = null;
        if ($user && ! empty($user->phone)) {
            $issued = $this->otp->issue($user->phone, 'recovery', $user->id);
            Cache::put(self::PREFIX.$recoveryId, [
                'user_id' => $user->id,
                'otp_id' => $issued['otp']->id,
                'verified' => false,
            ], (int) config('otp.verification_ttl_seconds', 1800));
            $code = $this->exposeCode() ? $issued['code'] : null;

            $this->audit->log('recovery.started', actorId: $user->id, resourceType: 'user', resourceId: $user->id);
        } else {
            // Store a decoy session so timing/shape match the real path.
            Cache::put(self::PREFIX.$recoveryId, ['user_id' => null, 'otp_id' => null, 'verified' => false],
                (int) config('otp.verification_ttl_seconds', 1800));
            $this->audit->log('recovery.started', result: 'failure', metadata: ['service_number' => $serviceNumber]);
        }

        return ['recovery_id' => $recoveryId, 'code' => $code];
    }

    /**
     * Step 2 — verify the OTP.
     */
    public function verify(string $recoveryId, string $code): string
    {
        $session = Cache::get(self::PREFIX.$recoveryId);
        if (! $session || ! $session['otp_id']) {
            return 'invalid';
        }
        $otp = OtpVerification::find($session['otp_id']);
        if (! $otp) {
            return 'invalid';
        }

        $result = $this->otp->verify($otp, $code);
        if ($result === 'verified') {
            $session['verified'] = true;
            Cache::put(self::PREFIX.$recoveryId, $session, (int) config('otp.verification_ttl_seconds', 1800));
        }

        return $result;
    }

    /**
     * Step 3 — set a new PIN, register this device, revoke all other sessions.
     *
     * @param  array{name: string, platform: string, model?: string, os_version?: string, app_version?: string}  $device
     * @return array{user: User, token: string}
     */
    public function reset(string $recoveryId, string $pin, array $device): array
    {
        $session = Cache::get(self::PREFIX.$recoveryId);
        abort_if(! $session || ! ($session['verified'] ?? false), 422, 'Recovery not verified.');
        abort_if(! $session['user_id'], 422, 'Recovery not verified.');

        $user = User::findOrFail($session['user_id']);

        // New credential, and revoke every existing session/device — a recovery
        // implies the previous device may be lost or compromised.
        $user->forceFill(['pin_hash' => Hash::make($pin)])->save();
        $user->tokens()->delete();
        $user->devices()->update(['status' => Device::STATUS_REVOKED]);

        $newDevice = Device::create([
            'user_id' => $user->id,
            'name' => $device['name'],
            'platform' => $device['platform'],
            'model' => $device['model'] ?? null,
            'os_version' => $device['os_version'] ?? null,
            'app_version' => $device['app_version'] ?? null,
            'last_active_at' => Carbon::now(),
            'status' => Device::STATUS_ACTIVE,
        ]);

        $token = $user->createToken($device['name'], ['officer']);
        $token->accessToken->forceFill(['device_id' => $newDevice->id])->save();

        Cache::forget(self::PREFIX.$recoveryId);

        $this->audit->log('recovery.completed', actorId: $user->id, resourceType: 'user',
            resourceId: $user->id, deviceId: $newDevice->id);
        $this->audit->security('account_recovered', userId: $user->id, severity: 'warning',
            deviceId: $newDevice->id);

        return ['user' => $user, 'token' => $token->plainTextToken];
    }

    private function exposeCode(): bool
    {
        return ! app()->environment('production') && (bool) config('otp.expose_in_response', false);
    }
}
