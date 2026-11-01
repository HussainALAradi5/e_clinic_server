<?php

use App\Enums\Communication\TicketConfirmation;
use App\Enums\Identity\AttachmentCategory;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Support tickets, round two: the steps that solved a ticket (a list), the requester's confirmation (awaiting,
     * confirmed, declined) and when it came, and files on tickets (screenshots, photos, PDF) through the attachments
     * module — a new `ticket_attachment` category.
     */
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->json('resolution_steps')->nullable();
            $table->string('confirmation', 10)->nullable();
            $table->timestampTz('confirmed_at')->nullable();
        });
        Constraints::enum('support_tickets', 'confirmation', TicketConfirmation::class, nullable: true);

        foreach (['attachments', 'upload_sessions'] as $table) {
            if (Schema::hasColumn($table, 'category')) {
                Constraints::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS chk_{$table}_category");
                Constraints::enum($table, 'category', AttachmentCategory::class);
            }
        }
    }

    public function down(): void
    {
        Constraints::statement('ALTER TABLE support_tickets DROP CONSTRAINT IF EXISTS chk_support_tickets_confirmation');
        Schema::table('support_tickets', fn (Blueprint $table) => $table->dropColumn(['resolution_steps', 'confirmation', 'confirmed_at']));
    }
};
