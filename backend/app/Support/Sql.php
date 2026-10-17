<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * The few places where PostgreSQL and MySQL/MariaDB need different SQL.
 *
 * PostgreSQL is the main database (Docker stack, Render). MySQL/MariaDB is
 * supported for shared hosting, where it is what the control panel offers
 * most comfortably. Everything else goes through the query builder, which
 * already speaks both.
 */
final class Sql
{
    public static function isPostgres(?string $connection = null): bool
    {
        return DB::connection($connection)->getDriverName() === 'pgsql';
    }

    /** Adds a CHECK constraint that $column holds digits only (Service Numbers). */
    public static function addDigitsOnlyCheck(string $table, string $column, string $name): void
    {
        $test = self::isPostgres() ? "{$column} ~ '^[0-9]+$'" : "{$column} REGEXP '^[0-9]+$'";
        DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$test})");
    }
}
