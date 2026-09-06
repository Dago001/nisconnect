<?php

namespace App\Services\Otp;

interface OtpSenderInterface
{
    /** Deliver an OTP code to the given phone number. */
    public function send(string $phone, string $code): void;
}
