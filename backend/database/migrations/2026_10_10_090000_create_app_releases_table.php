<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mobile app builds uploaded by administrators and offered on the public
 * download page (/download). The newest published row per platform+variant
 * is the one served.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_releases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('platform');               // android
            $table->string('variant');                // standard (64-bit) | legacy (32-bit)
            $table->string('version');
            $table->text('notes')->nullable();
            $table->string('file_path');              // on the default disk, random name
            $table->string('original_name');
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->boolean('is_published')->default(true);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['platform', 'variant', 'is_published', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_releases');
    }
};
