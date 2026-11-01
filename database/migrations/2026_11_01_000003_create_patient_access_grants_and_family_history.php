<?php

use App\Enums\Identity\AuditAction;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Break the glass: a clinician opens a patient who is not theirs, with a reason, for a few hours; every grant is
     * kept (and audited) for review. Plus the patient's family and surgical history next to allergies and chronic
     * conditions (medical fields, shown with patients.view_medical only).
     */
    public function up(): void
    {
        Schema::create('patient_access_grants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 500);
            $table->timestampTz('expires_at');
            $table->timestampsTz();
            $table->index(['user_id', 'expires_at']);
            $table->index('patient_id');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->text('family_history')->nullable();
            $table->text('surgical_history')->nullable();
        });

        // The audit trail now also records `emergency_access` (PostgreSQL keeps the allowed actions in a check).
        Constraints::statement('ALTER TABLE audit_logs DROP CONSTRAINT IF EXISTS chk_audit_logs_action');
        Constraints::enum('audit_logs', 'action', AuditAction::class);
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropColumn(['family_history', 'surgical_history']);
        });
        Schema::dropIfExists('patient_access_grants');
    }
};
