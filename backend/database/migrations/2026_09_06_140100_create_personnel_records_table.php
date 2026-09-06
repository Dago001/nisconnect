<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Local cache/mirror of authorised NIS personnel records. Populated from the
 * configured personnel provider at verification time. The authoritative source
 * remains the NIS personnel system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('service_number', 20)->unique(); // digits only, leading zeroes preserved
            $table->string('surname');
            $table->string('first_name');
            $table->string('other_name')->nullable();
            $table->string('rank')->nullable();
            $table->string('directorate')->nullable();
            $table->string('department')->nullable();
            $table->string('zone')->nullable();
            $table->string('command')->nullable();
            $table->string('formation')->nullable();
            $table->string('unit')->nullable();
            $table->string('posting')->nullable();
            $table->string('official_email')->nullable();
            $table->string('status')->default('active'); // active|retired|suspended|dismissed
            $table->string('photo_path')->nullable();
            $table->string('source')->default('demo');    // demo|api|database
            $table->timestamp('source_synced_at')->nullable();
            $table->timestamps();

            $table->index('surname');
            $table->index('rank');
            $table->index('directorate');
            $table->index('command');
        });

        DB::statement("ALTER TABLE personnel_records ADD CONSTRAINT personnel_service_number_digits CHECK (service_number ~ '^[0-9]+$')");

        // Now that personnel_records exists, wire the FK from users.
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('personnel_record_id')->references('id')->on('personnel_records')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['personnel_record_id']);
        });
        Schema::dropIfExists('personnel_records');
    }
};
