<?php

declare(strict_types=1);

namespace App\Shared\Database;

use BackedEnum;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL-only DDL used by migrations (CHECK constraints, partial / expression / BRIN indexes, triggers).
 * On other drivers (SQLite in tests) these are skipped — the application validates the same rules.
 */
final class Constraints
{
    public static function isPostgres(): bool
    {
        return DB::connection()->getDriverName() === 'pgsql';
    }

    /** `CHECK (column IN (...enum values))` named `chk_<table>_<column>`. */
    public static function enum(string $table, string $column, string $enum, bool $nullable = false): void
    {
        /** @var class-string<BackedEnum> $enum */
        $values = implode(', ', array_map(fn (BackedEnum $case): string => "'".$case->value."'", $enum::cases()));
        $condition = "{$column} IN ({$values})";

        self::check($table, "chk_{$table}_{$column}", $nullable ? "{$column} IS NULL OR {$condition}" : $condition);
    }

    public static function check(string $table, string $name, string $expression): void
    {
        self::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
    }

    /** Runs raw DDL on PostgreSQL only. */
    public static function statement(string $sql): void
    {
        if (self::isPostgres()) {
            DB::statement($sql);
        }
    }

    /** Raises an exception on UPDATE / DELETE (append-only tables such as audit_logs). */
    public static function immutable(string $table): void
    {
        self::statement(<<<SQL
            CREATE OR REPLACE FUNCTION {$table}_immutable() RETURNS trigger AS \$\$
            BEGIN
                RAISE EXCEPTION '{$table} is append-only';
            END;
            \$\$ LANGUAGE plpgsql
            SQL);
        self::statement("CREATE TRIGGER trg_{$table}_immutable BEFORE UPDATE OR DELETE ON {$table} FOR EACH ROW EXECUTE FUNCTION {$table}_immutable()");
    }
}
