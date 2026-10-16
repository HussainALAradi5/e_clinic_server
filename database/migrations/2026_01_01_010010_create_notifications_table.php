<?php

use App\Enums\Identity\NotificationChannel;
use App\Enums\Identity\NotificationStatus;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Notifications: outbox + delivery log (one row per recipient per channel).
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('person_id')->constrained('persons')->restrictOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('template', 100);
            $table->char('locale', 2)->default('en');
            $table->string('destination')->nullable();
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('related_type', 50)->nullable();
            $table->uuid('related_id')->nullable();
            $table->string('status', 20)->default(NotificationStatus::Queued->value);
            $table->timestampTz('scheduled_for')->useCurrent();
            $table->smallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->string('provider_message_id', 100)->nullable()->index();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['related_type', 'related_id'], 'notifications_related_index');
            $table->index(['person_id', 'created_at']);
            $table->unique(['person_id', 'channel', 'template', 'related_type', 'related_id', 'scheduled_for'], 'notifications_dedupe_unique');
        });

        Constraints::enum('notifications', 'channel', NotificationChannel::class);
        Constraints::enum('notifications', 'status', NotificationStatus::class);
        Constraints::check('notifications', 'chk_notifications_attempts', 'attempts BETWEEN 0 AND 10');
        Constraints::check('notifications', 'chk_notifications_destination', "channel = 'in_app' OR destination IS NOT NULL");
        Constraints::statement("CREATE INDEX notifications_due_index ON notifications (scheduled_for) WHERE status = 'queued'");
        Constraints::statement("CREATE INDEX notifications_inbox_index ON notifications (user_id, read_at) WHERE channel = 'in_app'");
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
