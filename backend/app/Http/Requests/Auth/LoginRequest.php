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
            'service_number' => ['required', 'string', new ServiceNumber],
            // At least one credential must be supplied.
            'pin' => ['nullable', 'string', 'required_without:password'],
            'password' => ['nullable', 'string', 'required_without:pin'],
            'device' => ['required', 'array'],
            'device.name' => ['required', 'string', 'max:120'],
            'device.platform' => ['required', 'in:android,ios,web'],
        ];
    }
}
