<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('platform');            // android|ios|web
            $table->string('model')->nullable();
            $table->string('os_version')->nullable();
            $table->string('app_version')->nullable();
            $table->string('push_token')->nullable();
            $table->timestamp('last_active_at')->nullable();
            $table->string('status')->default('active'); // active|revoked
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('push_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('provider'); // fcm|apns
            $table->string('token');
            $table->timestamps();

            $table->unique(['provider', 'token']);
        });

        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone', 20);
            $table->string('purpose')->default('onboarding'); // onboarding|login|recovery
            $table->string('code_hash');    // hashed OTP — never plaintext
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts')->default(5);
            $table->timestamp('expires_at');
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedSmallInteger('resend_count')->default(0);
            $table->timestamps();

            $table->index(['phone', 'purpose']);
        });
    }

    public function down(): void
    {
        foreach (['otp_verifications', 'push_tokens', 'devices'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
