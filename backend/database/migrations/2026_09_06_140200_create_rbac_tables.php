<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Role-based access control with organisational scoping.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique(); // super_admin|nis_admin|directorate_admin|group_admin|security_admin|officer
            $table->string('label');
            $table->string('scope_type')->nullable(); // directorate|zone|command|... (nullable = global)
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('label');
            $table->string('group')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained('roles')->cascadeOnDelete();
            // Organisational scope for scoped admin roles.
            $table->string('scope_type')->nullable();
            $table->uuid('scope_id')->nullable();
            $table->uuid('granted_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'scope_type', 'scope_id'], 'user_roles_unique_scope');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        foreach (['user_roles', 'role_permissions', 'permissions', 'roles'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
