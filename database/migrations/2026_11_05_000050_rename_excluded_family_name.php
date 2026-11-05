<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Demo data restored before the seeder change still carries a family name the demo must not use (the name itself is
 * never written here: rows are found by the SHA-256 of their lowercased family name). Gives those people an approved
 * family name (patient2 becomes Fatima Al-Najjar, as the seeder now makes her) and updates the copies of their full
 * names in journal descriptions and audit history. Those two tables refuse edits on PostgreSQL, so their guard
 * triggers are off for this one correction only, inside the same transaction. Does nothing where the name is absent.
 */
return new class extends Migration
{
    private const EXCLUDED_SHA256 = '3ccdeb63e9d18e9962185ff3e4634d2490af43a9bf1368fd20845201f84dbf21';

    /** First name => [new first name, new family name]; anyone else gets FALLBACK. */
    private const RENAMES = [
        'Fatema' => ['Fatima', 'Al-Najjar'],
        'Mohammed' => ['Mohammed', 'Al-Haddad'],
        'Hussain' => ['Hussain', 'Al-Aradi'],
        'Zahra' => ['Zahra', 'Al-Mulla'],
        'Isa' => ['Isa', 'Al-Mukhtar'],
    ];

    private const FALLBACK = 'Al-Najjar';

    public function up(): void
    {
        $people = DB::table('persons')->whereNotNull('last_name')->get(['id', 'first_name', 'last_name'])
            ->filter(fn (object $p): bool => hash('sha256', mb_strtolower((string) $p->last_name)) === self::EXCLUDED_SHA256);
        if ($people->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($people): void {
            $replacements = [];
            foreach ($people as $person) {
                [$first, $last] = self::RENAMES[$person->first_name] ?? [$person->first_name, self::FALLBACK];
                DB::table('persons')->where('id', $person->id)->update(['first_name' => $first, 'last_name' => $last]);
                $replacements["{$person->first_name} {$person->last_name}"] = "{$first} {$last}";
                $replacements[(string) $person->last_name] = self::FALLBACK;
            }
            // Full names first, then any family name left on its own.
            uksort($replacements, fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
            $excluded = (string) $people->first()->last_name;

            $pgsql = DB::getDriverName() === 'pgsql';
            if ($pgsql) {
                DB::statement('ALTER TABLE journal_entries DISABLE TRIGGER trg_je_immutable');
                DB::statement('ALTER TABLE audit_logs DISABLE TRIGGER trg_audit_logs_immutable');
            }
            foreach ([['journal_entries', 'description'], ['audit_logs', 'new_values'], ['audit_logs', 'old_values']] as [$table, $column]) {
                $rows = DB::table($table)->whereRaw("CAST({$column} AS TEXT) LIKE ?", ['%'.$excluded.'%'])->get(['id', $column]);
                foreach ($rows as $row) {
                    DB::table($table)->where('id', $row->id)->update([$column => strtr((string) $row->{$column}, $replacements)]);
                }
            }
            if ($pgsql) {
                // The journal's deferred balance check runs now, so the guard can be switched back on.
                DB::statement('SET CONSTRAINTS ALL IMMEDIATE');
                DB::statement('ALTER TABLE journal_entries ENABLE TRIGGER trg_je_immutable');
                DB::statement('ALTER TABLE audit_logs ENABLE TRIGGER trg_audit_logs_immutable');
            }
        });
    }

    public function down(): void
    {
        // A correction of demo data: nothing to undo.
    }
};
