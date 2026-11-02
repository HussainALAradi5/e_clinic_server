<?php

use App\Enums\HR\AttendanceSource;
use App\Enums\HR\AttendanceStatus;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * HR › Attendance: one row per employee per day (check-in / check-out, lateness, overtime); the nightly close fills
     * the days nobody recorded. Rows of a month in an approved payroll are locked.
     */
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('date');
            $table->timestampTz('check_in_at')->nullable();
            $table->timestampTz('check_out_at')->nullable();
            $table->unsignedInteger('worked_minutes')->default(0);
            $table->unsignedInteger('late_minutes')->default(0);
            $table->unsignedInteger('overtime_minutes')->default(0);
            $table->boolean('overtime_approved')->default(false);
            $table->string('status', 20);
            $table->string('source', 20);
            $table->string('check_in_ip', 45)->nullable();
            $table->string('notes', 500)->nullable();
            $table->boolean('needs_review')->default(false);
            $table->boolean('is_locked')->default(false);
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestampsTz();
            $table->unique(['employee_id', 'date'], 'attendances_employee_date_unique');
            $table->index(['date', 'status']);
        });

        Constraints::enum('attendances', 'status', AttendanceStatus::class);
        Constraints::enum('attendances', 'source', AttendanceSource::class);
        Constraints::check('attendances', 'chk_attendances_out_after_in', 'check_out_at IS NULL OR check_out_at > check_in_at');
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
