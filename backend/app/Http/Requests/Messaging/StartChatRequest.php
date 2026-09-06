<?php

namespace App\Http\Requests\Messaging;

use App\Rules\ServiceNumber;
use Illuminate\Foundation\Http\FormRequest;

class StartChatRequest extends FormRequest
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
