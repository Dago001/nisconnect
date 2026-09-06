<?php

namespace App\Http\Requests\Auth;

use App\Rules\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_number' => ['required', 'string', new ServiceNumber()],
            'pin' => ['nullable', 'string'],
            'password' => ['nullable', 'string'],
            'device' => ['required', 'array'],
            'device.name' => ['required', 'string', 'max:120'],
            'device.platform' => ['required', 'in:android,ios,web'],
        ];
    }
}
