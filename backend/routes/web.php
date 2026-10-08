<?php

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\Admin\AnnouncementController;
use App\Http\Controllers\Admin\AuditController;
use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\ChannelAdminController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DeviceAdminController;
use App\Http\Controllers\Admin\GroupAdminController;
use App\Http\Controllers\Admin\OfficerController;
use App\Http\Controllers\Admin\OrgStructureController;
use App\Http\Controllers\Admin\PersonnelController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SystemController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('admin.login'));

// --- NISconnect Administration Portal ----------------------------------------
// Session auth + per-route permissions (perm:). See App\Support\AdminPermissions.
Route::prefix('admin')->name('admin.')->middleware('admin.headers')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->name('login.submit')->middleware('throttle:admin-login');
        Route::get('two-factor', [AuthController::class, 'showChallenge'])->name('two-factor.challenge');
        Route::post('two-factor', [AuthController::class, 'challenge'])->name('two-factor.verify')->middleware('throttle:admin-login');
    });
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::middleware(['auth', 'admin.portal'])->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard')->middleware('perm:dashboard.view');

        // My account (every administrator)
        Route::get('account', [AccountController::class, 'show'])->name('account');
        Route::get('account/password', [AccountController::class, 'showPassword'])->name('account.password');
        Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
        Route::get('account/two-factor', [AccountController::class, 'showTwoFactor'])->name('account.two-factor');
        Route::post('account/two-factor', [AccountController::class, 'enableTwoFactor'])->name('account.two-factor.enable');
        Route::post('account/two-factor/confirm', [AccountController::class, 'confirmTwoFactor'])->name('account.two-factor.confirm');
        Route::post('account/two-factor/recovery-codes', [AccountController::class, 'regenerateRecoveryCodes'])->name('account.two-factor.recovery');
        Route::delete('account/two-factor', [AccountController::class, 'disableTwoFactor'])->name('account.two-factor.disable');

        // Officers (registered accounts)
        Route::middleware('perm:officers.view')->group(function () {
            Route::get('officers', [OfficerController::class, 'index'])->name('officers.index');
            Route::get('officers/export', [OfficerController::class, 'export'])->name('officers.export')->middleware('perm:officers.export');
            Route::get('officers/{user}', [OfficerController::class, 'show'])->name('officers.show');
        });
        Route::middleware('perm:officers.manage')->group(function () {
            Route::patch('officers/{user}', [OfficerController::class, 'update'])->name('officers.update');
            Route::post('officers/{user}/suspend', [OfficerController::class, 'suspend'])->name('officers.suspend');
            Route::post('officers/{user}/reactivate', [OfficerController::class, 'reactivate'])->name('officers.reactivate');
            Route::post('officers/{user}/disable', [OfficerController::class, 'disable'])->name('officers.disable');
            Route::post('officers/{user}/unlock', [OfficerController::class, 'unlock'])->name('officers.unlock');
            Route::post('officers/{user}/sign-out', [OfficerController::class, 'signOutEverywhere'])->name('officers.sign-out');
        });

        // Administrators & roles
        Route::middleware('perm:admins.manage')->group(function () {
            Route::get('administrators', [AdministratorController::class, 'index'])->name('administrators.index');
            Route::post('administrators', [AdministratorController::class, 'store'])->name('administrators.store');
            Route::delete('administrators/roles/{userRole}', [AdministratorController::class, 'revoke'])->name('administrators.revoke');
            Route::post('administrators/{user}/reset-password', [AdministratorController::class, 'resetPassword'])->name('administrators.reset-password');
            Route::post('administrators/{user}/reset-two-factor', [AdministratorController::class, 'resetTwoFactor'])->name('administrators.reset-two-factor');
        });
        Route::middleware('perm:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });

        // Personnel records (mirror of the authorised NIS source)
        Route::middleware('perm:personnel.view')->group(function () {
            Route::get('personnel', [PersonnelController::class, 'index'])->name('personnel.index');
            Route::get('personnel/export', [PersonnelController::class, 'export'])->name('personnel.export');
        });
        Route::middleware('perm:personnel.import')->group(function () {
            Route::get('personnel/import', [PersonnelController::class, 'showImport'])->name('personnel.import');
            Route::post('personnel/import', [PersonnelController::class, 'import'])->name('personnel.import.store');
            Route::get('personnel/import/template', [PersonnelController::class, 'template'])->name('personnel.import.template');
        });
        Route::middleware('perm:personnel.manage')->group(function () {
            Route::get('personnel/create', [PersonnelController::class, 'create'])->name('personnel.create');
            Route::post('personnel', [PersonnelController::class, 'store'])->name('personnel.store');
            Route::get('personnel/{personnel}/edit', [PersonnelController::class, 'edit'])->name('personnel.edit');
            Route::put('personnel/{personnel}', [PersonnelController::class, 'update'])->name('personnel.update');
        });

        // Organisation structure
        Route::middleware('perm:org.manage')->group(function () {
            Route::get('organisation', [OrgStructureController::class, 'index'])->name('org.index');
            Route::post('organisation/{type}', [OrgStructureController::class, 'store'])->name('org.store');
            Route::put('organisation/{type}/{id}', [OrgStructureController::class, 'update'])->name('org.update');
            Route::delete('organisation/{type}/{id}', [OrgStructureController::class, 'destroy'])->name('org.destroy');
        });

        // Groups
        Route::middleware('perm:groups.manage')->group(function () {
            Route::get('groups', [GroupAdminController::class, 'index'])->name('groups.index');
            Route::post('groups', [GroupAdminController::class, 'store'])->name('groups.store');
            Route::get('groups/{group}', [GroupAdminController::class, 'show'])->name('groups.show');
            Route::put('groups/{group}', [GroupAdminController::class, 'update'])->name('groups.update');
            Route::delete('groups/{group}', [GroupAdminController::class, 'destroy'])->name('groups.destroy');
            Route::post('groups/{group}/members', [GroupAdminController::class, 'addMember'])->name('groups.members.store');
            Route::delete('groups/{group}/members/{user}', [GroupAdminController::class, 'removeMember'])->name('groups.members.destroy');
        });

        // Official channels
        Route::middleware('perm:channels.manage')->group(function () {
            Route::get('channels', [ChannelAdminController::class, 'index'])->name('channels.index');
            Route::post('channels', [ChannelAdminController::class, 'store'])->name('channels.store');
            Route::get('channels/{channel}', [ChannelAdminController::class, 'show'])->name('channels.show');
            Route::put('channels/{channel}', [ChannelAdminController::class, 'update'])->name('channels.update');
            Route::delete('channels/{channel}', [ChannelAdminController::class, 'destroy'])->name('channels.destroy');
            Route::post('channels/{channel}/members', [ChannelAdminController::class, 'addMember'])->name('channels.members.store');
            Route::delete('channels/{channel}/members/{user}', [ChannelAdminController::class, 'removeMember'])->name('channels.members.destroy');
        });

        // Announcements
        Route::middleware('perm:announcements.send')->group(function () {
            Route::get('announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('announcements', [AnnouncementController::class, 'store'])->name('announcements.store');
        });

        // Moderation
        Route::middleware('perm:reports.review')->group(function () {
            Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
            Route::get('reports/{report}', [ReportController::class, 'show'])->name('reports.show');
            Route::post('reports/{report}/action', [ReportController::class, 'action'])->name('reports.action');
        });

        // Devices
        Route::get('devices', [DeviceAdminController::class, 'index'])->name('devices.index')->middleware('perm:devices.view');
        Route::post('devices/{device}/revoke', [DeviceAdminController::class, 'revoke'])->name('devices.revoke')->middleware('perm:devices.revoke');

        // Audit & security
        Route::get('audit', [AuditController::class, 'auditLogs'])->name('audit.index')->middleware('perm:audit.view');
        Route::get('audit/export', [AuditController::class, 'exportAudit'])->name('audit.export')->middleware('perm:audit.export');
        Route::get('security', [AuditController::class, 'securityEvents'])->name('security.index')->middleware('perm:security.view');
        Route::get('security/export', [AuditController::class, 'exportSecurity'])->name('security.export')->middleware('perm:audit.export');

        // System
        Route::get('settings', [SettingsController::class, 'index'])->name('settings.index')->middleware('perm:settings.manage');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update')->middleware('perm:settings.manage');
        Route::get('system', [SystemController::class, 'index'])->name('system.index')->middleware('perm:system.view');
    });
});
