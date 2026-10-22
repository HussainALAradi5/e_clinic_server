<?php

use App\Enums\Transactions\CashSessionStatus;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transactions › Cash drawer shifts. The expected cash is computed from the session's documents at close.
     */
    public function up(): void
    {
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->foreignUuid('cashier_id')->index()->constrained('users')->restrictOnDelete();
            $table->timestampTz('opened_at');
            $table->money('opening_float')->default(0);
            $table->money('expected_closing')->nullable();
            $table->money('counted_closing')->nullable();
            $table->money('difference')->nullable();
            $table->json('denominations')->nullable();
            $table->string('status', 20)->default(CashSessionStatus::Open->value);
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('approved_by')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->money('deposit_amount')->nullable();
            $table->date('deposited_at')->nullable();
            $table->string('deposit_reference', 100)->nullable();
            $table->foreignUuid('adjustment_journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('deposit_journal_entry_id')->nullable()->index()->constrained('journal_entries')->nullOnDelete();
            $table->string('notes', 500)->nullable();
            $table->index('opened_at');
            $table->index('deleted_by');
        });

        Constraints::enum('cash_sessions', 'status', CashSessionStatus::class);
        Constraints::check('cash_sessions', 'chk_cash_sessions_float', 'opening_float >= 0');
        Constraints::statement("CREATE UNIQUE INDEX cash_sessions_one_open_per_cashier ON cash_sessions (cashier_id) WHERE status IN ('open', 'closing') AND NOT is_deleted");
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_sessions');
    }
};
