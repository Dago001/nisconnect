<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class CreateGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'members' => ['nullable', 'array', 'max:500'],
            'members.*' => ['uuid'],
        ];
    }
}
