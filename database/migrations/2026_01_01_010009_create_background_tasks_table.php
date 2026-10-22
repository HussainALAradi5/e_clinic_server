<?php

use App\Enums\Identity\TaskCancelReason;
use App\Enums\Identity\TaskType;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Background tasks: exports, ZIPs, bulk actions … with progress, result file and cancellation.
     */
    public function up(): void
    {
        Schema::create('background_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40);
            $table->string('status', 20)->default(GenericStatus::Pending->value);
            $table->smallInteger('progress')->default(0);
            $table->jsonb('parameters');
            $table->string('subject_type', 50)->nullable();
            $table->uuid('subject_id')->nullable();
            $table->string('result_path', 500)->nullable();
            $table->jsonb('result_summary')->nullable();
            $table->string('cancel_reason', 20)->nullable();
            $table->string('error_message', 1000)->nullable();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->timestampsTz();

            $table->index(['requested_by', 'created_at']);
            $table->index(['status', 'finished_at']);
        });

        Constraints::enum('background_tasks', 'type', TaskType::class);
        Constraints::enum('background_tasks', 'status', GenericStatus::class);
        Constraints::enum('background_tasks', 'cancel_reason', TaskCancelReason::class, nullable: true);
        Constraints::check('background_tasks', 'chk_background_tasks_progress', 'progress BETWEEN 0 AND 100');
        Constraints::check('background_tasks', 'chk_background_tasks_cancel_state', "(status = 'cancelled') = (cancel_reason IS NOT NULL)");
        Constraints::statement("CREATE UNIQUE INDEX background_tasks_active_subject_unique ON background_tasks (type, subject_type, subject_id) WHERE status IN ('pending', 'in_progress') AND subject_id IS NOT NULL");
    }

    public function down(): void
    {
        Schema::dropIfExists('background_tasks');
    }
};
