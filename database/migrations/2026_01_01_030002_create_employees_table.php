<?php

use App\Enums\HR\EmployeeStatus;
use App\Enums\HR\EmploymentType;
use App\Enums\HR\SalaryComponentType;
use App\Enums\HR\TerminationReason;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR › Employees: subtype of users (same id as users and persons) holding HR data only, plus recurring salary
     * components; then the department manager link (departments ↔ employees reference each other).
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreign('id')->references('id')->on('users')->restrictOnDelete();
            $table->foreignUuid('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('job_title', 150);
            $table->string('employment_type', 20);
            $table->string('status', 20)->default(EmployeeStatus::Active->value);
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('termination_reason', 30)->nullable();
            $table->time('work_start_time')->default('08:00');
            $table->time('work_end_time')->default('16:00');
            $table->json('weekly_off_days');
            $table->money('basic_salary');
            $table->string('bank_name', 100)->nullable();
            $table->text('iban')->nullable();
            $table->string('social_insurance_number', 30)->nullable();
            $table->version();
            $table->timestampsTz();
            $table->index(['department_id', 'status'], 'employees_department_status_index');
        });

        Schema::create('employee_salary_components', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('employee_id')->index()->constrained('employees')->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('name', 100);
            $table->money('amount');
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_taxable')->default(false);
        });

        Schema::table('departments', function (Blueprint $table) {
            $table->foreignUuid('manager_id')->nullable()->index()->constrained('employees')->nullOnDelete();
        });

        Constraints::enum('employees', 'employment_type', EmploymentType::class);
        Constraints::enum('employees', 'status', EmployeeStatus::class);
        Constraints::enum('employees', 'termination_reason', TerminationReason::class, nullable: true);
        Constraints::check('employees', 'chk_employees_salary', 'basic_salary >= 0');
        Constraints::check('employees', 'chk_employees_termination', 'termination_date IS NULL OR termination_date >= hire_date');
        Constraints::enum('employee_salary_components', 'type', SalaryComponentType::class);
        Constraints::check('employee_salary_components', 'chk_esc_amount', 'amount > 0');
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
        });
        Schema::dropIfExists('employee_salary_components');
        Schema::dropIfExists('employees');
    }
};
