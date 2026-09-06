<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type')->default('text'); // text|image|video|document|audio|voice|system
            $table->text('body')->nullable();
            $table->uuid('reply_to_id')->nullable();
            $table->uuid('forwarded_from_id')->nullable();
            $table->timestamp('edited_at')->nullable();
            $table->timestamp('pinned_at')->nullable();
            $table->string('status')->default('sent'); // sending|sent|delivered|read|failed
            $table->timestamps();
            $table->softDeletes();

            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_id');
        });

        // Full-text search index over message bodies (GIN) for indexed search.
        DB::statement("CREATE INDEX messages_body_fts ON messages USING gin (to_tsvector('simple', coalesce(body, '')))");

        Schema::create('message_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('kind'); // image|video|document|audio|voice
            $table->timestamps();
        });

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji');
            $table->timestamps();

            $table->unique(['message_id', 'user_id', 'emoji']);
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('read_at')->useCurrent();

            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('message_mentions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('mentioned_user_id');
        });

        Schema::create('voice_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->unsignedInteger('duration_ms');
            $table->jsonb('waveform')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['voice_notes', 'message_mentions', 'message_reads', 'message_reactions', 'message_attachments', 'messages'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
