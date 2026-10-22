<?php

use App\Enums\HR\PayrollStatus;
use App\Enums\HR\PayslipItemType;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR › Payroll: one active run per month (draft → calculated → approved → paid), a payslip per employee with its
     * line items. Approval posts the salary accrual, payment the bank settlement.
     */
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->string('status', 20)->default(PayrollStatus::Draft->value);
            $table->money('total_gross')->default(0);
            $table->money('total_deductions')->default(0);
            $table->money('total_net')->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->timestampTz('calculated_at')->nullable();
            $table->uuid('calculated_by')->nullable()->index();
            $table->uuid('approved_by')->nullable()->index();
            $table->timestampTz('approved_at')->nullable();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->foreignUuid('accrual_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->foreignUuid('payment_journal_entry_id')->nullable()->constrained('journal_entries')->nullOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->index(['period_year', 'period_month']);
            $table->foreign('calculated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
        });

        Constraints::enum('payroll_runs', 'status', PayrollStatus::class);
        Constraints::check('payroll_runs', 'chk_payroll_runs_month', 'period_month BETWEEN 1 AND 12');
        Constraints::statement("CREATE UNIQUE INDEX payroll_runs_period_unique ON payroll_runs (period_year, period_month) WHERE status <> 'cancelled'");

        Schema::create('payslips', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
            $table->foreignUuid('employee_id')->index()->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('department_id')->index()->constrained('departments')->restrictOnDelete();
            $table->money('basic_salary');
            $table->unsignedSmallInteger('working_days');
            $table->decimal('present_days', 4, 1);
            $table->decimal('absent_days', 4, 1);
            $table->decimal('unpaid_leave_days', 4, 1);
            $table->decimal('paid_leave_days', 4, 1)->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->money('total_allowances');
            $table->money('overtime_amount');
            $table->money('gross_salary');
            $table->money('total_deductions');
            $table->money('net_salary');
            $table->money('carried_forward')->default(0);
            $table->timestampsTz();
            $table->unique(['payroll_run_id', 'employee_id'], 'payslips_run_employee_unique');
        });

        Constraints::check('payslips', 'chk_payslips_net', 'net_salary >= 0');
        Constraints::check('payslips', 'chk_payslips_math', 'net_salary = gross_salary - total_deductions');

        Schema::create('payslip_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('payslip_id')->index()->constrained('payslips')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->money('amount');
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestampsTz();
        });

        Constraints::enum('payslip_items', 'type', PayslipItemType::class);
        Constraints::check('payslip_items', 'chk_payslip_items_amount', 'amount >= 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('payslip_items');
        Schema::dropIfExists('payslips');
        Schema::dropIfExists('payroll_runs');
    }
};
