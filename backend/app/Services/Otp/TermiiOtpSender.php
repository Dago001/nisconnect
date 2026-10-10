<?php

namespace App\Services\Otp;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Sends OTPs through Termii (https://termii.com), a Nigerian SMS gateway.
 * The API key and sender ID come from .env; nothing secret lives in code.
 * Failures are logged without the phone number or the code.
 */
class TermiiOtpSender implements OtpSenderInterface
{
    /**
     * @param  array{base_url: ?string, key: ?string, sender_id: ?string, channel: ?string}  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly array $config,
    ) {}

    public function send(string $phone, string $code): void
    {
        $key = (string) ($this->config['key'] ?? '');
        if ($key === '') {
            Log::warning('Termii OTP not sent: TERMII_API_KEY is not set.');

            return;
        }

        try {
            $response = $this->http
                ->timeout(10)
                ->asJson()
                ->post(rtrim((string) ($this->config['base_url'] ?: 'https://v3.api.termii.com'), '/').'/api/sms/send', [
                    'api_key' => $key,
                    'to' => self::international($phone),
                    'from' => $this->config['sender_id'] ?: 'NISconnect',
                    'sms' => "Your NISconnect verification code is {$code}. It expires shortly. Do not share it.",
                    'type' => 'plain',
                    'channel' => $this->config['channel'] ?: 'dnd',
                ]);
            if ($response->failed()) {
                Log::warning('Termii OTP not sent.', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::warning('Termii OTP not sent: '.class_basename($e));
        }
    }

    /** 08031234567 / +2348031234567 / 2348031234567 → 2348031234567 (Termii's format). */
    public static function international(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            return '234'.substr($digits, 1);
        }

        return $digits;
    }
}
