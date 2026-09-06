<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserAdminController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

// --- NISconnect Administration Portal (web session + RBAC) ------------------
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.submit');
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'role:super_admin,nis_admin,security_admin,directorate_admin'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
        Route::get('users', [UserAdminController::class, 'index'])->name('users');
        Route::post('users/{user}/suspend', [UserAdminController::class, 'suspend'])->name('users.suspend');
        Route::post('users/{user}/reactivate', [UserAdminController::class, 'reactivate'])->name('users.reactivate');
        Route::post('users/{user}/revoke-devices', [UserAdminController::class, 'revokeDevices'])->name('users.revoke');
    });
});
