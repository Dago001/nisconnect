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
            // Voice-note metadata (only meaningful when type=voice).
            'duration_ms' => ['nullable', 'integer', 'min:1', 'max:600000'],
            'waveform' => ['nullable', 'array', 'max:512'],
            'waveform.*' => ['numeric'],
        ];
    }
}
