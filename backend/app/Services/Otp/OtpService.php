<?php

namespace App\Services\Otp;

use App\Models\OtpVerification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Secure OTP generation, delivery and verification.
 *
 * - Codes are stored only as Argon2id hashes (never plaintext).
 * - Enforces expiry, attempt limits, resend delay and resend caps.
 * - Verification is server-side only.
 */
class OtpService
{
    public function __construct(private readonly OtpSenderInterface $sender)
    {
    }

    /**
     * Issue (or re-issue) an OTP for a phone/purpose.
     *
     * @return array{otp: OtpVerification, code: string}
     *
     * @throws RuntimeException When the resend delay or resend cap is hit.
     */
    public function issue(string $phone, string $purpose = 'onboarding', ?string $userId = null): array
    {
        $existing = OtpVerification::where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('created_at')
            ->first();

        $now = Carbon::now();

        if ($existing && $existing->last_sent_at) {
            $delay = (int) config('otp.resend_delay_seconds', 60);
            if ($existing->last_sent_at->diffInSeconds($now, true) < $delay) {
                throw new RuntimeException('resend_delay');
            }
            if ($existing->resend_count >= (int) config('otp.max_resends', 3)) {
                throw new RuntimeException('resend_limit');
            }
        }

        $code = $this->generateCode();

        $otp = OtpVerification::create([
            'user_id' => $userId,
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'max_attempts' => (int) config('otp.max_attempts', 5),
            'expires_at' => $now->copy()->addSeconds((int) config('otp.ttl_seconds', 300)),
            'last_sent_at' => $now,
            'resend_count' => $existing ? $existing->resend_count + 1 : 0,
        ]);

        $this->sender->send($phone, $code);

        return ['otp' => $otp, 'code' => $code];
    }

    /**
     * Verify a submitted code against a stored OTP record.
     *
     * @return string One of: verified|expired|consumed|too_many_attempts|invalid
     */
    public function verify(OtpVerification $otp, string $code): string
    {
        if ($otp->isConsumed()) {
            return 'consumed';
        }
        if ($otp->isExpired()) {
            return 'expired';
        }
        if ($otp->attempts >= $otp->max_attempts) {
            return 'too_many_attempts';
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->increment('attempts');

            return $otp->attempts >= $otp->max_attempts ? 'too_many_attempts' : 'invalid';
        }

        $otp->update(['consumed_at' => Carbon::now()]);

        return 'verified';
    }

    private function generateCode(): string
    {
        $length = (int) config('otp.length', 6);
        $max = (10 ** $length) - 1;

        return str_pad((string) random_int(0, $max), $length, '0', STR_PAD_LEFT);
    }
}
