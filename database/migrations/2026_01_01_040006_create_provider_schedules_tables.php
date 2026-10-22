<?php

use App\Enums\Appointments\ScheduleExceptionType;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Schedules: weekly working blocks of everyone who takes bookings, and date exceptions
     * (day off, custom hours, clinic-wide holidays with provider_id NULL). Times are clinic local time.
     */
    public function up(): void
    {
        Schema::create('provider_schedules', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('provider_id')->constrained('employees')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedSmallInteger('slot_minutes')->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->index(['provider_id', 'day_of_week'], 'provider_schedules_provider_day_index');
        });

        Schema::create('schedule_exceptions', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->foreignUuid('provider_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->date('date');
            $table->string('type', 20);
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('reason', 255)->nullable();
            $table->index(['provider_id', 'date'], 'schedule_exceptions_provider_date_index');
        });

        Constraints::check('provider_schedules', 'chk_provider_schedules_time', 'end_time > start_time');
        Constraints::check('provider_schedules', 'chk_provider_schedules_dates', 'effective_to IS NULL OR effective_to >= effective_from');
        Constraints::check('provider_schedules', 'chk_provider_schedules_day_of_week', 'day_of_week BETWEEN 0 AND 6');
        Constraints::enum('schedule_exceptions', 'type', ScheduleExceptionType::class);
        Constraints::check('schedule_exceptions', 'chk_schedule_exceptions_hours', "type <> 'custom_hours' OR (start_time IS NOT NULL AND end_time > start_time)");
        // One live exception per provider and date (clinic-wide holidays included).
        Constraints::statement('CREATE UNIQUE INDEX schedule_exceptions_provider_date_unique ON schedule_exceptions (provider_id, date) NULLS NOT DISTINCT WHERE is_deleted = false');
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_exceptions');
        Schema::dropIfExists('provider_schedules');
    }
};
