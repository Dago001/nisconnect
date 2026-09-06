<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class ConfirmIdentityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verification_id' => ['required', 'uuid'],
            // E.164-ish Nigerian/international format.
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{7,15}$/'],
        ];
    }
}
