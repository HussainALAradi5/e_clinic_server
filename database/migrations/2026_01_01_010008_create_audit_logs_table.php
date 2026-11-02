<?php

use App\Enums\Identity\AuditAction;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Audit Logs: append-only trail (UPDATE / DELETE blocked by a trigger on PostgreSQL).
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 30);
            $table->string('auditable_type')->nullable();
            $table->uuid('auditable_id')->nullable();
            $table->string('module', 50);
            $table->jsonb('old_values')->nullable();
            $table->jsonb('new_values')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->uuid('request_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index(['module', 'action']);
        });

        Constraints::enum('audit_logs', 'action', AuditAction::class);
        Constraints::statement('CREATE INDEX audit_logs_created_at_brin ON audit_logs USING brin (created_at)');
        Constraints::immutable('audit_logs');
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
