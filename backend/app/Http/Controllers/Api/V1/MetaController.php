<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Public, unauthenticated service information. `moved_to` is the https
 * address the service now lives at (APP_CANONICAL_URL), or null. The mobile
 * apps check it and switch their saved server address, so a domain change
 * needs no reinstall.
 */
class MetaController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $canonical = rtrim((string) config('app.canonical_url'), '/');

        return response()->json([
            'name' => 'NISconnect',
            'api_version' => 'v1',
            'moved_to' => str_starts_with($canonical, 'https://') ? $canonical : null,
        ])->header('Cache-Control', 'no-store');
    }
}
