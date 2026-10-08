<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Administration portal: admin account hardening (2FA, password rotation),
 * system settings, announcements and moderation notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('two_factor_secret')->nullable();          // encrypted
            $table->text('two_factor_recovery_codes')->nullable();  // encrypted JSON of hashes
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('password_changed_at')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('last_admin_login_at')->nullable();
            $table->string('last_admin_login_ip', 45)->nullable();
        });

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->jsonb('value')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('announcements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('body');
            $table->string('audience_type')->default('all'); // all|directorate|command|zone|rank
            $table->string('audience_value')->nullable();
            $table->string('priority')->default('normal');   // normal|urgent
            $table->unsignedInteger('recipients_count')->default(0);
            $table->uuid('sent_by')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->text('resolution_note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reports', fn (Blueprint $t) => $t->dropColumn('resolution_note'));
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('system_settings');
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn([
            'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at',
            'password_changed_at', 'must_change_password', 'last_admin_login_at', 'last_admin_login_ip',
        ]));
    }
};
