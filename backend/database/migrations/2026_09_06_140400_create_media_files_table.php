<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('disk')->default('private');
            $table->string('path');            // never a public web path
            $table->string('mime');
            $table->string('extension', 16);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('checksum')->nullable();
            $table->string('scan_status')->default('pending'); // pending|clean|infected|failed
            $table->timestamps();

            $table->index(['owner_id', 'scan_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
