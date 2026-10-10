<?php

namespace App\Http\Requests\Admin;

use App\Models\AppRelease;
use App\Services\Admin\AppReleaseService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAppReleaseRequest extends FormRequest
{
    /** 200 MB, in kilobytes (Laravel's file `max` unit). */
    public const MAX_KB = 204800;

    public function authorize(): bool
    {
        return (bool) $this->user()?->hasPermission('downloads.manage');
    }

    public function rules(): array
    {
        return [
            'variant' => ['required', Rule::in(array_keys(AppRelease::VARIANTS))],
            'version' => ['required', 'string', 'max:30', 'regex:/^[0-9A-Za-z.+-]+$/'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'file' => ['required', 'file', 'max:'.self::MAX_KB, 'extensions:apk',
                function (string $attribute, mixed $value, Closure $fail) {
                    // Check the bytes, not the client-supplied MIME type.
                    if (! $value instanceof UploadedFile || ! AppReleaseService::looksLikeApk($value)) {
                        $fail('That file is not a valid Android app (APK). Upload the .apk file produced by the build.');
                    }
                }],
        ];
    }

    public function messages(): array
    {
        return [
            'variant.required' => 'Choose which phones this build is for.',
            'variant.in' => 'Choose which phones this build is for.',
            'version.required' => 'Enter the version number, for example 1.0.0.',
            'version.max' => 'Keep the version to 30 characters or fewer.',
            'version.regex' => 'Use only letters, numbers, dots, plus and minus signs in the version (for example 1.0.0).',
            'notes.max' => 'Keep the notes to 2,000 characters or fewer.',
            'file.required' => 'Choose the .apk file to upload.',
            'file.uploaded' => 'The file did not upload. It may be larger than the server allows ('
                .ini_get('upload_max_filesize').' per file) — check the PHP upload limits, then try again.',
            'file.max' => 'The app file is too big. The limit is 200 MB.',
            'file.extensions' => 'The file must be an Android app with the .apk extension.',
        ];
    }
}
