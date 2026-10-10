<?php

namespace App\Rules;

use App\Services\Admin\SettingsService;
use Illuminate\Validation\Rules\Password;

/** Administrator password policy (length from settings, mixed case, numbers, symbols). */
final class AdminPassword
{
    public static function rule(): Password
    {
        $min = (int) app(SettingsService::class)->get('admin_password_min_length');

        return Password::min($min)->mixedCase()->numbers()->symbols();
    }
}
