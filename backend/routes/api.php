<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CallController;
use App\Http\Controllers\Api\V1\ChannelController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Controllers\Api\V1\MediaController;
use App\Http\Controllers\Api\V1\MessageController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\SafetyController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // --- Public onboarding & auth (rate limited, anti-enumeration) ---------
    Route::prefix('auth')->group(function () {
        Route::post('verify-service-number', [AuthController::class, 'verifyServiceNumber'])
            ->middleware('throttle:verify');
        Route::post('confirm-identity', [AuthController::class, 'confirmIdentity'])
            ->middleware('throttle:otp');
        Route::post('resend-otp', [AuthController::class, 'resendOtp'])
            ->middleware('throttle:otp');
        Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
            ->middleware('throttle:otp');
        Route::post('set-credentials', [AuthController::class, 'setCredentials'])
            ->middleware('throttle:verify');
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login');
    });

    // --- Authenticated ------------------------------------------------------
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);

        Route::get('users/me', [UserController::class, 'me']);
        Route::patch('users/me', [UserController::class, 'update']);
        Route::put('users/me/privacy', [UserController::class, 'updatePrivacy']);

        Route::get('directory/search', [DirectoryController::class, 'search'])
            ->middleware('throttle:directory');
        Route::get('directory/{serviceNumber}', [DirectoryController::class, 'show'])
            ->middleware('throttle:directory');

        Route::get('devices', [DeviceController::class, 'index']);
        Route::post('devices/push-token', [DeviceController::class, 'registerPushToken']);
        Route::delete('devices/all', [DeviceController::class, 'destroyAll']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);

        // Chats & messaging
        Route::get('chats', [ChatController::class, 'index']);
        Route::post('chats', [ChatController::class, 'start']);
        Route::get('chats/{conversation}', [ChatController::class, 'show']);
        Route::get('chats/{conversation}/messages', [MessageController::class, 'index']);
        Route::post('chats/{conversation}/messages', [MessageController::class, 'store']);
        Route::post('chats/{conversation}/typing', [MessageController::class, 'typing']);
        Route::get('messages/search', [MessageController::class, 'search']);
        Route::post('messages/{message}/read', [MessageController::class, 'markRead']);
        Route::post('messages/{message}/react', [MessageController::class, 'react']);
        Route::delete('messages/{message}', [MessageController::class, 'destroy']);

        // Groups
        Route::get('groups', [GroupController::class, 'index']);
        Route::post('groups', [GroupController::class, 'store']);
        Route::post('groups/{group}/members', [GroupController::class, 'addMembers']);
        Route::delete('groups/{group}/members/{userId}', [GroupController::class, 'removeMember']);
        Route::post('groups/{group}/leave', [GroupController::class, 'leave']);

        // Media
        Route::post('media', [MediaController::class, 'upload']);
        Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');

        // Calls (LiveKit signalling)
        Route::post('calls', [CallController::class, 'store']);
        Route::get('calls/history', [CallController::class, 'history']);
        Route::get('calls/{call}/token', [CallController::class, 'token']);
        Route::post('calls/{call}/answer', [CallController::class, 'answer']);
        Route::post('calls/{call}/decline', [CallController::class, 'decline']);
        Route::post('calls/{call}/end', [CallController::class, 'end']);

        // Official channels
        Route::get('channels', [ChannelController::class, 'index']);
        Route::get('channels/{channel}', [ChannelController::class, 'show']);
        Route::get('channels/{channel}/posts', [ChannelController::class, 'posts']);
        Route::post('channels/{channel}/posts', [ChannelController::class, 'publish']);
        Route::post('channels/{channel}/subscribe', [ChannelController::class, 'subscribe']);

        // Safety
        Route::post('safety/block', [SafetyController::class, 'block']);
        Route::post('safety/unblock', [SafetyController::class, 'unblock']);
        Route::post('safety/report', [SafetyController::class, 'report']);

        // Notifications
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::post('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::post('notifications/{notification}/read', [NotificationController::class, 'read']);
    });
});
