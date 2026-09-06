<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');           // direct|group|channel
            $table->string('title')->nullable();
            $table->string('avatar_path')->nullable();
            // Soft links (FKs added by groups/channels tables to avoid circularity).
            $table->uuid('group_id')->nullable()->index();
            $table->uuid('channel_id')->nullable()->index();
            $table->uuid('last_message_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
        });

        Schema::create('conversation_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // member|admin|owner
            $table->timestamp('muted_until')->nullable();
            $table->uuid('last_read_message_id')->nullable();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamp('left_at')->nullable();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('type')->default('standard'); // standard|organisational
            $table->string('org_scope_type')->nullable();
            $table->uuid('org_scope_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->jsonb('permissions')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['org_scope_type', 'org_scope_id']);
        });

        Schema::create('group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('member'); // owner|admin|moderator|member
            $table->jsonb('permissions')->nullable();
            $table->uuid('added_by')->nullable();
            $table->timestamps();

            $table->unique(['group_id', 'user_id']);
        });

        Schema::create('channels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('avatar_path')->nullable();
            $table->string('org_scope_type')->nullable();
            $table->uuid('org_scope_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('channel_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('channel_id')->constrained('channels')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('subscriber'); // publisher|subscriber
            $table->timestamps();

            $table->unique(['channel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        foreach (['channel_members', 'channels', 'group_members', 'groups', 'conversation_members', 'conversations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
