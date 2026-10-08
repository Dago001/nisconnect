<?php

namespace App\Services\Admin;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238) two-factor authentication for administrators, compatible
 * with Google Authenticator, Microsoft Authenticator, Authy, etc.
 */
class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa = new Google2FA) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(
            config('app.name', 'NISconnect').' Admin',
            $user->service_number,
            $secret,
        );
    }

    /** Inline SVG QR code for the authenticator app to scan. */
    public function qrSvg(string $url): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd));

        return $writer->writeString($url);
    }

    public function verify(string $secret, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        return strlen($code) === 6 && $this->google2fa->verifyKey($secret, $code, 1);
    }

    /**
     * Fresh single-use recovery codes. Returns [plain codes to show once, hashes to store].
     *
     * @return array{0: list<string>, 1: list<string>}
     */
    public function makeRecoveryCodes(int $count = 8): array
    {
        $plain = [];
        for ($i = 0; $i < $count; $i++) {
            $plain[] = Str::upper(Str::random(5).'-'.Str::random(5));
        }

        return [$plain, array_map(fn ($c) => Hash::make($c), $plain)];
    }

    /** Consumes a recovery code if it matches. */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $code = Str::upper(trim($code));
        $hashes = $user->two_factor_recovery_codes ?? [];
        foreach ($hashes as $i => $hash) {
            if (Hash::check($code, $hash)) {
                unset($hashes[$i]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($hashes)])->save();

                return true;
            }
        }

        return false;
    }
}
