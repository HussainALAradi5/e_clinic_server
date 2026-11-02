<?php

use App\Enums\Shared\ApprovalStatus;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR › Leaves: configurable types, yearly balances per employee (remaining = entitled + carried − used − pending,
     * computed) and requests through the shared approval workflow.
     */
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->decimal('annual_quota_days', 5, 1)->default(0);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->decimal('max_carry_forward_days', 5, 1)->default(0);
            $table->unsignedSmallInteger('min_notice_days')->default(0);
        });

        Constraints::check('leave_types', 'chk_leave_types_days', 'annual_quota_days >= 0 AND max_carry_forward_days >= 0');

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignUuid('leave_type_id')->index()->constrained('leave_types')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->decimal('entitled_days', 5, 1);
            $table->decimal('carried_forward_days', 5, 1)->default(0);
            $table->decimal('used_days', 5, 1)->default(0);
            $table->decimal('pending_days', 5, 1)->default(0);
            $table->timestampsTz();
            $table->unique(['employee_id', 'leave_type_id', 'year'], 'leave_balances_unique');
        });

        Constraints::check('leave_balances', 'chk_leave_balances_non_negative', 'used_days >= 0 AND pending_days >= 0');

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignUuid('leave_type_id')->index()->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('total_days', 5, 1);
            $table->boolean('is_half_day')->default(false);
            $table->string('reason', 1000)->nullable();
            $table->string('status', 20)->default(ApprovalStatus::Pending->value);
            $table->uuid('reviewed_by')->nullable()->index();
            $table->timestampTz('reviewed_at')->nullable();
            $table->string('review_notes', 1000)->nullable();
            $table->index(['employee_id', 'status']);
            $table->index(['start_date', 'end_date']);
            $table->foreign('reviewed_by')->references('id')->on('users')->nullOnDelete();
        });

        Constraints::enum('leave_requests', 'status', ApprovalStatus::class);
        Constraints::check('leave_requests', 'chk_leave_requests_dates', 'end_date >= start_date');
        Constraints::check('leave_requests', 'chk_leave_requests_days', 'total_days > 0');
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_types');
    }
};
