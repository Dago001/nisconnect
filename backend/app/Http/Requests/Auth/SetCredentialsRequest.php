<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SetCredentialsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_id' => ['required', 'uuid'],
            'pin' => ['required', 'string', 'min:4', 'max:12', 'regex:/^[0-9]+$/'],
            'password' => ['nullable', 'string', 'min:8', 'max:128'],
            'device' => ['required', 'array'],
            'device.name' => ['required', 'string', 'max:120'],
            'device.platform' => ['required', 'in:android,ios,web'],
            'device.model' => ['nullable', 'string', 'max:120'],
            'device.os_version' => ['nullable', 'string', 'max:60'],
            'device.app_version' => ['nullable', 'string', 'max:60'],
        ];
    }
}
