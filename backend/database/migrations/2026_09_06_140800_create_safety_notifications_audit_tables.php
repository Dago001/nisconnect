<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blocked_users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('blocker_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['blocker_id', 'blocked_id']);
        });

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type'); // user|message|group
            $table->uuid('target_id');
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('open'); // open|reviewing|actioned|dismissed
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });

        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('title');
            $table->text('body')->nullable();
            $table->jsonb('data')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });

        // Append-only audit trail. No updates/deletes in application code.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable();
            $table->string('action');
            $table->string('resource_type')->nullable();
            $table->uuid('resource_id')->nullable();
            $table->string('result')->default('success'); // success|failure|denied
            $table->string('ip', 45)->nullable();
            $table->uuid('device_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
            $table->index(['resource_type', 'resource_id']);
        });

        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();
            $table->string('event');
            $table->string('severity')->default('info'); // info|warning|critical
            $table->string('ip', 45)->nullable();
            $table->uuid('device_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'created_at']);
            $table->index('severity');
        });
    }

    public function down(): void
    {
        foreach (['security_events', 'audit_logs', 'notifications', 'reports', 'blocked_users'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
