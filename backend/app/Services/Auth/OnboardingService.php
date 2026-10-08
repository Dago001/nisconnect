<?php

namespace App\Services\Auth;

use App\Models\Device;
use App\Models\OtpVerification;
use App\Models\User;
use App\Personnel\PersonnelRecordData;
use App\Services\Admin\SettingsService;
use App\Services\Otp\OtpService;
use App\Services\Support\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Orchestrates the Service Number onboarding flow across its steps. Interim
 * state lives in a short-lived, server-side verification session (cache),
 * never on the client, and no account exists until every step passes.
 */
class OnboardingService
{
    public function __construct(
        private readonly PersonnelVerificationService $personnel,
        private readonly OtpService $otp,
        private readonly AuditLogger $audit,
        private readonly SettingsService $settings,
        private readonly DeviceLimitService $deviceLimit,
    ) {}

    private const PREFIX = 'onboarding:';

    public const REGISTRATION_CLOSED_MESSAGE = 'Registration is currently closed. Contact your administrator.';

    /**
     * Step 1 — begin a verification session for a found & authorised record.
     */
    public function begin(PersonnelRecordData $record): string
    {
        $id = (string) Str::uuid();
        $this->putSession($id, [
            'service_number' => $record->serviceNumber,
            'record' => $record->toArray(),
            'identity_confirmed' => false,
            'phone' => null,
            'otp_id' => null,
            'phone_verified' => false,
        ]);

        $this->personnel->mirror($record);
        $this->audit->log('service_number.verified', resourceType: 'personnel_record',
            metadata: ['service_number' => $record->serviceNumber, 'authorised' => true]);

        return $id;
    }

    /**
     * Step 2 — officer confirms identity and provides a phone; issue OTP.
     *
     * @return array{code: ?string}
     */
    public function confirmIdentity(string $verificationId, string $phone): array
    {
        $session = $this->requireSession($verificationId);
        $session['identity_confirmed'] = true;
        $session['phone'] = $phone;

        $issued = $this->otp->issue($phone, 'onboarding');
        $session['otp_id'] = $issued['otp']->id;
        $this->putSession($verificationId, $session);

        return ['code' => $this->exposeCode() ? $issued['code'] : null];
    }

    /**
     * Re-send the OTP for the current session.
     *
     * @return array{code: ?string}
     */
    public function resendOtp(string $verificationId): array
    {
        $session = $this->requireSession($verificationId);
        abort_unless($session['identity_confirmed'] && $session['phone'], 422, 'Identity not confirmed.');

        $issued = $this->otp->issue($session['phone'], 'onboarding');
        $session['otp_id'] = $issued['otp']->id;
        $this->putSession($verificationId, $session);

        return ['code' => $this->exposeCode() ? $issued['code'] : null];
    }

    /**
     * Step 3 — verify the OTP.
     */
    public function verifyOtp(string $verificationId, string $code): string
    {
        $session = $this->requireSession($verificationId);
        $otp = OtpVerification::find($session['otp_id'] ?? null);
        if (! $otp) {
            return 'invalid';
        }

        $result = $this->otp->verify($otp, $code);
        if ($result === 'verified') {
            $session['phone_verified'] = true;
            $this->putSession($verificationId, $session);
        }

        return $result;
    }

    /**
     * Step 4 — create the account, register the device, issue a token.
     *
     * @param  array{name: string, platform: string, model?: string, os_version?: string, app_version?: string}  $device
     * @return array{user: User, device: Device, token: string}
     */
    public function complete(string $verificationId, string $pin, array $device, ?string $password = null): array
    {
        $session = $this->requireSession($verificationId);
        abort_unless($session['phone_verified'] ?? false, 422, 'Phone not verified.');
        // Registration may have been closed after this session started.
        abort_if(! $this->settings->get('registration_open')
            && ! User::where('service_number', $session['service_number'])->exists(),
            403, self::REGISTRATION_CLOSED_MESSAGE);

        $recordData = $session['record'];

        return DB::transaction(function () use ($session, $recordData, $pin, $password, $device, $verificationId) {
            $personnel = $this->personnel->mirror(PersonnelRecordData::fromArrayLike($recordData));

            $user = User::create([
                'personnel_record_id' => $personnel->id,
                'service_number' => $session['service_number'],
                'phone' => $session['phone'],
                'phone_verified_at' => Carbon::now(),
                'display_name' => trim($recordData['first_name'].' '.$recordData['surname']),
                'account_state' => User::STATE_ACTIVE,
                'privacy' => User::defaultPrivacy(),
            ]);
            $user->forceFill([
                'pin_hash' => Hash::make($pin),
                'password_hash' => $password ? Hash::make($password) : null,
            ])->save();

            $deviceModel = Device::create([
                'user_id' => $user->id,
                'name' => $device['name'],
                'platform' => $device['platform'],
                'model' => $device['model'] ?? null,
                'os_version' => $device['os_version'] ?? null,
                'app_version' => $device['app_version'] ?? null,
                'last_active_at' => Carbon::now(),
                'status' => Device::STATUS_ACTIVE,
            ]);

            $token = $user->createToken(
                $device['name'],
                ['officer'],
            );
            // Bind token to device.
            $token->accessToken->forceFill(['device_id' => $deviceModel->id])->save();

            $this->deviceLimit->enforce($user, $deviceModel);

            $this->forgetSession($verificationId);

            $this->audit->log('account.created', actorId: $user->id, resourceType: 'user',
                resourceId: $user->id, deviceId: $deviceModel->id);
            $this->audit->log('device.registered', actorId: $user->id, resourceType: 'device',
                resourceId: $deviceModel->id, deviceId: $deviceModel->id);
            $this->audit->security('new_device_login', userId: $user->id, deviceId: $deviceModel->id,
                metadata: ['platform' => $device['platform']]);

            return ['user' => $user, 'device' => $deviceModel, 'token' => $token->plainTextToken];
        });
    }

    // --- session helpers -------------------------------------------------

    /** @return array<string, mixed> */
    private function requireSession(string $id): array
    {
        $session = Cache::get(self::PREFIX.$id);
        abort_if($session === null, 422, 'This verification session has expired. Please start again.');

        return $session;
    }

    /** @param array<string, mixed> $data */
    private function putSession(string $id, array $data): void
    {
        Cache::put(self::PREFIX.$id, $data, (int) config('otp.verification_ttl_seconds', 1800));
    }

    private function forgetSession(string $id): void
    {
        Cache::forget(self::PREFIX.$id);
    }

    private function exposeCode(): bool
    {
        return ! app()->environment('production') && (bool) config('otp.expose_in_response', false);
    }
}
