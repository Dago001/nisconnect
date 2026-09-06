<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ConfirmIdentityRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\SetCredentialsRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Http\Requests\Auth\VerifyServiceNumberRequest;
use App\Http\Resources\PersonnelResource;
use App\Http\Resources\UserResource;
use App\Models\Device;
use App\Models\User;
use App\Personnel\PersonnelSourceUnavailableException;
use App\Services\Auth\OnboardingService;
use App\Services\Auth\PersonnelVerificationService;
use App\Services\Auth\RecoveryService;
use App\Services\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AuthController extends Controller
{
    public function __construct(
        private readonly PersonnelVerificationService $personnel,
        private readonly OnboardingService $onboarding,
        private readonly RecoveryService $recovery,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Step 1 — verify a numeric Service Number against the personnel source.
     * The not-found and not-authorised paths return an identical generic
     * response to prevent Service Number enumeration.
     */
    public function verifyServiceNumber(VerifyServiceNumberRequest $request): JsonResponse
    {
        $serviceNumber = $request->validated('service_number');

        try {
            $record = $this->personnel->lookup($serviceNumber);
        } catch (PersonnelSourceUnavailableException) {
            return response()->json([
                'message' => 'The personnel verification service is temporarily unavailable. Please try again later.',
            ], 503);
        }

        if ($record === null || ! $record->isAuthorised()) {
            $this->audit->log('service_number.verify_failed', resourceType: 'personnel_record',
                result: 'failure', metadata: ['service_number' => $serviceNumber]);

            return response()->json([
                'verified' => false,
                'message' => 'We could not verify this Service Number. Please check the number and try again.',
            ]);
        }

        $verificationId = $this->onboarding->begin($record);

        return response()->json([
            'verified' => true,
            'verification_id' => $verificationId,
            'record' => new PersonnelResource($record),
        ]);
    }

    /**
     * Step 2 — officer confirms identity and provides a phone; OTP is sent.
     */
    public function confirmIdentity(ConfirmIdentityRequest $request): JsonResponse
    {
        try {
            $result = $this->onboarding->confirmIdentity(
                $request->validated('verification_id'),
                $request->validated('phone'),
            );
        } catch (RuntimeException $e) {
            return $this->otpThrottleResponse($e);
        }

        return response()->json(array_filter([
            'otp_sent' => true,
            'message' => 'A verification code has been sent to your phone.',
            'debug_code' => $result['code'],
        ], fn ($v) => $v !== null));
    }

    public function resendOtp(ResendOtpRequest $request): JsonResponse
    {
        try {
            $result = $this->onboarding->resendOtp($request->validated('verification_id'));
        } catch (RuntimeException $e) {
            return $this->otpThrottleResponse($e);
        }

        return response()->json(array_filter([
            'otp_sent' => true,
            'debug_code' => $result['code'],
        ], fn ($v) => $v !== null));
    }

    /**
     * Step 3 — verify the OTP.
     */
    public function verifyOtp(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->onboarding->verifyOtp(
            $request->validated('verification_id'),
            $request->validated('code'),
        );

        return match ($result) {
            'verified' => response()->json(['verified' => true]),
            'expired' => response()->json(['verified' => false, 'message' => 'This code has expired. Please request a new one.'], 422),
            'too_many_attempts' => response()->json(['verified' => false, 'message' => 'Too many attempts. Please request a new code.'], 429),
            'consumed' => response()->json(['verified' => false, 'message' => 'This code has already been used.'], 422),
            default => response()->json(['verified' => false, 'message' => 'The code you entered is incorrect.'], 422),
        };
    }

    /**
     * Step 4 — set PIN/password, register device, create the account.
     */
    public function setCredentials(SetCredentialsRequest $request): JsonResponse
    {
        $result = $this->onboarding->complete(
            $request->validated('verification_id'),
            $request->validated('pin'),
            $request->validated('device'),
            $request->validated('password'),
        );

        return response()->json([
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']->load('personnelRecord')),
        ], 201);
    }

    /**
     * Login by Service Number + PIN/password on a registered device.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = User::where('service_number', $data['service_number'])->first();

        $genericFail = fn () => response()->json(['message' => 'Invalid Service Number or credentials.'], 401);

        if (! $user) {
            $this->audit->log('login.failed', resourceType: 'user', result: 'failure',
                metadata: ['service_number' => $data['service_number']]);

            return $genericFail();
        }

        if (! $user->isActive()) {
            return response()->json(['message' => 'This account is not active. Please contact an administrator.'], 403);
        }

        $secret = $data['pin'] ?? $data['password'] ?? '';
        $hash = ! empty($data['pin']) ? $user->pin_hash : $user->password_hash;

        if (empty($hash) || ! Hash::check($secret, $hash)) {
            $this->audit->log('login.failed', actorId: $user->id, resourceType: 'user', result: 'failure');
            $this->audit->security('failed_login', userId: $user->id, severity: 'warning');

            return $genericFail();
        }

        $device = Device::firstOrCreate(
            ['user_id' => $user->id, 'name' => $data['device']['name'], 'platform' => $data['device']['platform']],
            ['last_active_at' => Carbon::now(), 'status' => Device::STATUS_ACTIVE],
        );
        $device->update(['last_active_at' => Carbon::now(), 'status' => Device::STATUS_ACTIVE]);

        $token = $user->createToken($data['device']['name'], ['officer']);
        $token->accessToken->forceFill(['device_id' => $device->id])->save();

        $user->update(['last_seen_at' => Carbon::now(), 'presence' => 'online']);

        $this->audit->log('login.success', actorId: $user->id, resourceType: 'user', deviceId: $device->id);

        return response()->json([
            'access_token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'user' => new UserResource($user->load('personnelRecord')),
        ]);
    }

    /**
     * Account recovery — step 1. Requires the registered phone (OTP), never the
     * Service Number alone. Generic response prevents account enumeration.
     */
    public function recoverStart(VerifyServiceNumberRequest $request): JsonResponse
    {
        $result = $this->recovery->start($request->validated('service_number'));

        // The recovery id is carried as `verification_id` through the next steps.
        return response()->json(array_filter([
            'verification_id' => $result['recovery_id'],
            'message' => 'If this Service Number has a registered phone, a code has been sent.',
            'debug_code' => $result['code'],
        ], fn ($v) => $v !== null));
    }

    public function recoverVerify(VerifyOtpRequest $request): JsonResponse
    {
        $result = $this->recovery->verify(
            $request->validated('verification_id'),
            $request->validated('code'),
        );

        return match ($result) {
            'verified' => response()->json(['verified' => true]),
            'too_many_attempts' => response()->json(['verified' => false, 'message' => 'Too many attempts.'], 429),
            'expired' => response()->json(['verified' => false, 'message' => 'This code has expired.'], 422),
            default => response()->json(['verified' => false, 'message' => 'The code you entered is incorrect.'], 422),
        };
    }

    public function recoverReset(SetCredentialsRequest $request): JsonResponse
    {
        $result = $this->recovery->reset(
            $request->validated('verification_id'),
            $request->validated('pin'),
            $request->validated('device'),
        );

        return response()->json([
            'access_token' => $result['token'],
            'token_type' => 'Bearer',
            'user' => new UserResource($result['user']->load('personnelRecord')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()->delete();
        $this->audit->log('logout', actorId: $user->id, resourceType: 'user');

        return response()->json(['message' => 'Signed out.']);
    }

    private function otpThrottleResponse(RuntimeException $e): JsonResponse
    {
        return match ($e->getMessage()) {
            'resend_delay' => response()->json(['message' => 'Please wait before requesting another code.'], 429),
            'resend_limit' => response()->json(['message' => 'Too many code requests. Please try again later.'], 429),
            default => response()->json(['message' => 'Unable to send verification code.'], 422),
        };
    }
}
