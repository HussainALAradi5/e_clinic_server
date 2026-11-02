<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Audit entries of fiscal years, fiscal periods, journal entries and shifts stored the model's class name (they had
 * no public morph alias). The app's classes moved to layer-first namespaces, so those names would no longer resolve:
 * store the aliases instead, as every other model does.
 */
return new class extends Migration
{
    private const ALIASES = [
        'App\\Modules\\Accounting\\Fiscal\\Models\\FiscalYear' => 'fiscal_year',
        'App\\Modules\\Accounting\\Fiscal\\Models\\FiscalPeriod' => 'fiscal_period',
        'App\\Modules\\Accounting\\Journal\\Models\\JournalEntry' => 'journal_entry',
        'App\\Modules\\HR\\Shifts\\Models\\Shift' => 'shift',
    ];

    public function up(): void
    {
        foreach (self::ALIASES as $class => $alias) {
            DB::table('audit_logs')->where('auditable_type', $class)->update(['auditable_type' => $alias]);
        }
    }

    public function down(): void
    {
        foreach (self::ALIASES as $class => $alias) {
            DB::table('audit_logs')->where('auditable_type', $alias)->update(['auditable_type' => $class]);
        }
    }
};
