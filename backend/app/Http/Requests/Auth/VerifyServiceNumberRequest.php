<?php

namespace App\Http\Requests\Auth;

use App\Rules\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;

class VerifyServiceNumberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_number' => ['required', 'string', new ServiceNumber],
        ];
    }
}
