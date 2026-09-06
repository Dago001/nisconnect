<?php

namespace App\Services\Otp;

use Illuminate\Support\Facades\Log;

/**
 * Development OTP sender. Writes the code to the log instead of sending an SMS.
 * Never use in production (configure a real SMS adapter via OTP_DRIVER=sms).
 */
class LogOtpSender implements OtpSenderInterface
{
    public function send(string $phone, string $code): void
    {
        Log::info('NISconnect OTP (dev)', ['phone' => $phone, 'code' => $code]);
    }
}
