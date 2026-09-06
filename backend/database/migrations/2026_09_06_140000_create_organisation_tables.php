<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * NIS organisational hierarchy:
 * Organisation > Directorates > Departments
 *              > Zones > Commands > Formations > Units
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('directorates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('directorate_id')->constrained('directorates')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('zones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->unique();
            $table->timestamps();
        });

        Schema::create('commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zone_id')->constrained('zones')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('formations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('command_id')->constrained('commands')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('type')->nullable(); // border|airport|seaport|hq|office
            $table->timestamps();
        });

        Schema::create('units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['units', 'formations', 'commands', 'zones', 'departments', 'directorates', 'organisations'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
