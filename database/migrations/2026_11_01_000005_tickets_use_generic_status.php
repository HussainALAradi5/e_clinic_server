<?php

use App\Enums\Appointments\TreatmentPriority;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Old ticket status => the shared GenericStatus it became. */
    private const MAP = ['open' => 'pending', 'in_progress' => 'in_progress', 'resolved' => 'completed', 'closed' => 'cancelled'];

    /** Old ticket priority => the shared TreatmentPriority it became. */
    private const PRIORITY = ['low' => 'medium', 'normal' => 'medium'];

    /**
     * Support tickets reuse the shared GenericStatus (pending, in progress, completed = solved, cancelled) and the
     * shared priority (medium, high, urgent) instead of their own enums. Databases created before the change get
     * their rows and check constraints converted.
     */
    public function up(): void
    {
        Constraints::statement('ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS chk_support_tickets_status');
        foreach (self::MAP as $old => $new) {
            DB::table('support_tickets')->where('status', $old)->update(['status' => $new]);
        }
        Constraints::statement("ALTER TABLE support_tickets ALTER COLUMN status SET DEFAULT '".GenericStatus::Pending->value."'");
        Constraints::enum('support_tickets', 'status', GenericStatus::class);

        Constraints::statement('ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS chk_support_tickets_priority');
        foreach (self::PRIORITY as $old => $new) {
            DB::table('support_tickets')->where('priority', $old)->update(['priority' => $new]);
        }
        Constraints::statement("ALTER TABLE support_tickets ALTER COLUMN priority SET DEFAULT '".TreatmentPriority::Medium->value."'");
        Constraints::enum('support_tickets', 'priority', TreatmentPriority::class);
    }

    public function down(): void
    {
        Constraints::statement('ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS chk_support_tickets_status');
        foreach (self::MAP as $old => $new) {
            DB::table('support_tickets')->where('status', $new)->update(['status' => $old]);
        }
    }
};
