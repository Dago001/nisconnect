<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NISconnect users are personnel-driven. A user is created only after a
        // Service Number is verified against the authorised NIS personnel source.
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('personnel_record_id')->nullable()->unique();
            // Digits only; VARCHAR preserves leading zeroes ("001234" stays "001234").
            $table->string('service_number', 20)->unique();
            // Stored encrypted (ciphertext is long), so use text not varchar(20).
            $table->text('phone')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->string('display_name');
            $table->string('avatar_path')->nullable();
            $table->string('password_hash')->nullable();   // Argon2id (optional login password)
            $table->string('pin_hash')->nullable();         // Argon2id (app PIN)
            $table->string('account_state')->default('pending'); // pending|active|suspended|locked|disabled|inactive
            $table->timestamp('last_seen_at')->nullable();
            $table->string('presence')->default('offline'); // online|offline|away
            $table->jsonb('privacy')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_state');
            $table->index('presence');
        });

        // Digit-only guarantee at the database layer.
        DB::statement("ALTER TABLE users ADD CONSTRAINT users_service_number_digits CHECK (service_number ~ '^[0-9]+$')");

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        // Laravel web session store (used by the admin portal).
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->uuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
