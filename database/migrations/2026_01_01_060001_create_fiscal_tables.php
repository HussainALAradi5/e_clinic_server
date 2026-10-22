<?php

use App\Enums\Accounting\FiscalStatus;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting › Fiscal years split into monthly posting periods (+ optional adjustment period 13).
     * Postings are only allowed into open periods.
     */
    public function up(): void
    {
        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('name', 20)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default(FiscalStatus::Open->value);
            // FK to journal_entries is added by the journal migration (created later).
            $table->uuid('closing_journal_entry_id')->nullable()->index();
            $table->foreignUuid('closed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->index('deleted_by');
        });

        Schema::create('fiscal_periods', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('fiscal_year_id')->constrained('fiscal_years')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_number');
            $table->string('name', 20);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status', 10)->default(FiscalStatus::Open->value);
            $table->foreignUuid('closed_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->unique(['fiscal_year_id', 'period_number'], 'fiscal_periods_year_number_unique');
            $table->index(['start_date', 'end_date'], 'fiscal_periods_dates_index');
            $table->index('deleted_by');
        });

        Constraints::check('fiscal_years', 'chk_fiscal_years_dates', 'end_date > start_date');
        Constraints::enum('fiscal_years', 'status', FiscalStatus::class);
        Constraints::statement("ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_no_overlap EXCLUDE USING gist (daterange(start_date, end_date, '[]') WITH &&) WHERE (NOT is_deleted)");
        Constraints::check('fiscal_periods', 'chk_fiscal_periods_dates', 'end_date >= start_date');
        Constraints::check('fiscal_periods', 'chk_fiscal_periods_number', 'period_number BETWEEN 1 AND 13');
        Constraints::enum('fiscal_periods', 'status', FiscalStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_periods');
        Schema::dropIfExists('fiscal_years');
    }
};
