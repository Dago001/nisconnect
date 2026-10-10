<?php

namespace App\Services\Otp;

/**
 * Sends nothing (OTP_DRIVER=none). For deployments without an SMS gateway
 * that show the sign-up code on screen instead (OTP_ONBOARDING_SHOW_CODE).
 * Unlike the log driver it never writes the code anywhere.
 */
class NullOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void {}
}
