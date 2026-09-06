<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('type');   // voice|video
            $table->string('mode');   // direct|group
            $table->foreignUuid('initiator_id')->constrained('users')->cascadeOnDelete();
            $table->string('room_name')->unique(); // LiveKit room
            $table->string('status')->default('calling'); // calling|ringing|connected|reconnecting|ended|missed|declined|failed
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['initiator_id', 'created_at']);
        });

        Schema::create('call_participants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('state')->default('ringing'); // ringing|joined|left|declined|missed
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['call_id', 'user_id']);
        });

        Schema::create('call_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('call_id')->constrained('calls')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('device_id')->nullable()->constrained('devices')->nullOnDelete();
            $table->string('connect_quality')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['call_sessions', 'call_participants', 'calls'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
