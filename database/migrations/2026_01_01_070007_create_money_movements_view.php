<?php

use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Transactions › v_money_movements: one read-only list of every money movement, built from the source documents
     * (payments, refunds, paid expenses, cash deposits and over / short). Not a second ledger — the journal is.
     * Payroll payments join it with the HR phase.
     */
    public function up(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_money_movements');
        DB::statement('CREATE VIEW v_money_movements AS '.$this->select());
    }

    public function down(): void
    {
        DB::statement('DROP VIEW IF EXISTS v_money_movements');
    }

    private function select(): string
    {
        $date = Constraints::isPostgres()
            ? fn (string $column): string => "({$column} AT TIME ZONE '".Config::string('clinic.timezone')."')::date"
            : fn (string $column): string => "date({$column})";

        return <<<SQL
            SELECT 'payment_in:' || p.id AS id, 'payment_in' AS movement_type, 'in' AS direction, p.amount AS amount, p.method AS method,
                   {$date('p.paid_at')} AS movement_date, p.paid_at AS occurred_at, 'payment' AS source_type, p.id AS source_id,
                   p.code AS source_code, p.journal_entry_id AS journal_entry_id, p.cash_session_id AS cash_session_id, p.patient_id AS patient_id
              FROM payments p WHERE p.status = 'completed' AND p.is_deleted = false
            UNION ALL
            SELECT 'refund_out:' || r.id, 'refund_out', 'out', r.amount, r.method, {$date('r.processed_at')}, r.processed_at, 'refund', r.id,
                   r.code, r.journal_entry_id, r.cash_session_id, i.patient_id
              FROM refunds r JOIN invoices i ON i.id = r.invoice_id WHERE r.processed_at IS NOT NULL AND r.is_deleted = false
            UNION ALL
            SELECT 'expense_out:' || e.id, 'expense_out', 'out', e.amount, e.payment_method, {$date('e.paid_at')}, e.paid_at, 'expense', e.id,
                   e.code, e.journal_entry_id, e.cash_session_id, NULL
              FROM expenses e WHERE e.paid_at IS NOT NULL AND e.is_deleted = false
            UNION ALL
            SELECT 'bank_deposit:' || c.id, 'bank_deposit', 'internal', c.deposit_amount, 'cash', c.deposited_at, c.updated_at, 'cash_session', c.id,
                   c.code, c.deposit_journal_entry_id, c.id, NULL
              FROM cash_sessions c WHERE c.deposited_at IS NOT NULL AND c.is_deleted = false
            UNION ALL
            SELECT 'cash_adjustment:' || c.id, 'cash_adjustment', CASE WHEN c.difference > 0 THEN 'in' ELSE 'out' END, abs(c.difference), 'cash',
                   {$date('c.closed_at')}, c.closed_at, 'cash_session', c.id, c.code, c.adjustment_journal_entry_id, c.id, NULL
              FROM cash_sessions c WHERE c.status = 'closed' AND c.difference <> 0 AND c.is_deleted = false
            SQL;
    }
};
