<?php

use App\Enums\Accounting\JournalEntryStatus;
use App\Enums\Accounting\JournalEntryType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounting › The double-entry general journal — the single financial ledger. Automatic entries come from
     * source documents (invoice, payment, refund, expense, cash session …); posted entries are immutable.
     */
    public function up(): void
    {
        Schema::create('journal_entries', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('entry_number', 20)->nullable()->unique();
            $table->date('entry_date');
            $table->foreignUuid('fiscal_period_id')->index()->constrained('fiscal_periods')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default(JournalEntryStatus::Draft->value);
            $table->string('source_type', 50)->nullable();
            $table->uuid('source_id')->nullable();
            $table->string('description', 500);
            $table->decimal('total_debit', 16, 3)->default(0);
            $table->decimal('total_credit', 16, 3)->default(0);
            $table->foreignUuid('reversal_of_id')->nullable()->unique()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('posted_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->index(['entry_date', 'status'], 'je_date_status_index');
            $table->index(['source_type', 'source_id'], 'je_source_index');
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->unsignedSmallInteger('line_number');
            $table->foreignUuid('account_id')->constrained('accounts')->restrictOnDelete();
            $table->decimal('debit', 16, 3)->default(0);
            $table->decimal('credit', 16, 3)->default(0);
            $table->string('description', 255)->nullable();
            $table->foreignUuid('department_id')->nullable()->index()->constrained('departments')->nullOnDelete();
            $table->foreignUuid('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->foreignUuid('doctor_id')->nullable()->index()->constrained('doctors')->nullOnDelete();
            $table->index('account_id', 'jel_account_index');
            $table->unique(['journal_entry_id', 'line_number'], 'jel_entry_line_unique');
        });

        Constraints::enum('journal_entries', 'type', JournalEntryType::class);
        Constraints::enum('journal_entries', 'status', JournalEntryStatus::class);
        Constraints::check('journal_entries', 'chk_je_balanced', "status = 'draft' OR total_debit = total_credit");
        Constraints::check('journal_entry_lines', 'chk_jel_amounts', 'debit >= 0 AND credit >= 0 AND (debit = 0) <> (credit = 0)');
        Constraints::statement('CREATE INDEX jel_patient_index ON journal_entry_lines (patient_id) WHERE patient_id IS NOT NULL');
        Constraints::statement('ALTER TABLE fiscal_years ADD CONSTRAINT fiscal_years_closing_journal_entry_id_foreign FOREIGN KEY (closing_journal_entry_id) REFERENCES journal_entries (id) ON DELETE SET NULL');

        // Posted entries are immutable: only `posted → reversed` (and its blame columns) may change.
        Constraints::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_entries_immutable() RETURNS trigger AS $$
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status <> 'draft' THEN RAISE EXCEPTION 'posted journal entries cannot be deleted'; END IF;
                    RETURN OLD;
                END IF;
                IF OLD.status <> 'draft' AND NOT (
                    OLD.status = 'posted' AND NEW.status = 'reversed'
                    AND NEW.entry_date = OLD.entry_date AND NEW.total_debit = OLD.total_debit AND NEW.total_credit = OLD.total_credit
                    AND NEW.entry_number IS NOT DISTINCT FROM OLD.entry_number AND NEW.fiscal_period_id = OLD.fiscal_period_id
                    AND NEW.is_deleted = OLD.is_deleted
                ) THEN
                    RAISE EXCEPTION 'posted journal entries are immutable (reverse them instead)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        Constraints::statement('CREATE TRIGGER trg_je_immutable BEFORE UPDATE OR DELETE ON journal_entries FOR EACH ROW EXECUTE FUNCTION journal_entries_immutable()');

        // Lines of a posted entry never change.
        Constraints::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_entry_lines_immutable() RETURNS trigger AS $$
            DECLARE entry_status varchar;
            BEGIN
                SELECT status INTO entry_status FROM journal_entries
                    WHERE id = CASE WHEN TG_OP = 'DELETE' THEN OLD.journal_entry_id ELSE NEW.journal_entry_id END;
                IF entry_status IS NOT NULL AND entry_status <> 'draft' THEN
                    RAISE EXCEPTION 'lines of a posted journal entry are immutable';
                END IF;
                RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        Constraints::statement('CREATE TRIGGER trg_jel_immutable BEFORE INSERT OR UPDATE OR DELETE ON journal_entry_lines FOR EACH ROW EXECUTE FUNCTION journal_entry_lines_immutable()');

        // At COMMIT: a posted entry's lines balance and match its totals.
        Constraints::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION journal_entries_balanced() RETURNS trigger AS $$
            DECLARE d numeric; c numeric;
            BEGIN
                IF NEW.status = 'draft' THEN RETURN NULL; END IF;
                SELECT coalesce(sum(debit), 0), coalesce(sum(credit), 0) INTO d, c FROM journal_entry_lines WHERE journal_entry_id = NEW.id;
                IF d <> c OR d <> NEW.total_debit OR c <> NEW.total_credit OR d = 0 THEN
                    RAISE EXCEPTION 'journal entry % is not balanced (debit %, credit %)', NEW.id, d, c;
                END IF;
                RETURN NULL;
            END;
            $$ LANGUAGE plpgsql
            SQL);
        Constraints::statement('CREATE CONSTRAINT TRIGGER trg_je_balanced AFTER INSERT OR UPDATE ON journal_entries DEFERRABLE INITIALLY DEFERRED FOR EACH ROW EXECUTE FUNCTION journal_entries_balanced()');
    }

    public function down(): void
    {
        Constraints::statement('ALTER TABLE fiscal_years DROP CONSTRAINT IF EXISTS fiscal_years_closing_journal_entry_id_foreign');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Constraints::statement('DROP FUNCTION IF EXISTS journal_entries_immutable() CASCADE');
        Constraints::statement('DROP FUNCTION IF EXISTS journal_entry_lines_immutable() CASCADE');
        Constraints::statement('DROP FUNCTION IF EXISTS journal_entries_balanced() CASCADE');
    }
};
