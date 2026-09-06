<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:text,image,video,document,audio,voice'],
            'body' => ['nullable', 'string', 'max:8000', 'required_if:type,text'],
            'reply_to_id' => ['nullable', 'uuid'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['uuid'],
        ];
    }
}
