<?php

use App\Enums\Communication\BubbleColor;
use App\Enums\Communication\BubbleStyle;
use App\Enums\Communication\MessageKind;
use App\Enums\Identity\AttachmentCategory;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Chat, round two: messages with files or a voice note (attachments of the message, through the attachments
     * module) and @mentions; one emoji reaction per person per message; each user's chat settings (bubble look,
     * whether chat counts in and pops into notifications); a profile photo on the person.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('kind', 10)->default(MessageKind::Text->value);
            $table->json('mentions')->nullable();
            $table->text('body')->nullable()->change();
        });
        Constraints::enum('messages', 'kind', MessageKind::class);

        Schema::create('message_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('message_id')->constrained('messages')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->timestampsTz();
            $table->unique(['message_id', 'user_id']);
        });

        Schema::create('chat_settings', function (Blueprint $table) {
            $table->foreignUuid('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('bubble_style', 10)->default(BubbleStyle::Rounded->value);
            $table->string('my_color', 10)->default(BubbleColor::Blue->value);
            $table->string('others_color', 10)->default(BubbleColor::Green->value);
            // Whether new messages pop up, and whether they count in the bell badge.
            $table->boolean('notify_messages')->default(true);
            $table->boolean('count_in_badge')->default(true);
            $table->timestampsTz();
        });
        Constraints::enum('chat_settings', 'bubble_style', BubbleStyle::class);
        Constraints::enum('chat_settings', 'my_color', BubbleColor::class);
        Constraints::enum('chat_settings', 'others_color', BubbleColor::class);

        Schema::table('persons', function (Blueprint $table) {
            $table->string('photo_path', 255)->nullable();
        });

        // New attachment categories (chat media, voice notes): PostgreSQL keeps the allowed values in checks.
        foreach (['attachments', 'upload_sessions'] as $table) {
            if (Schema::hasColumn($table, 'category')) {
                Constraints::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS chk_{$table}_category");
                Constraints::enum($table, 'category', AttachmentCategory::class);
            }
        }
    }

    public function down(): void
    {
        Schema::table('persons', fn (Blueprint $table) => $table->dropColumn('photo_path'));
        Schema::dropIfExists('chat_settings');
        Schema::dropIfExists('message_reactions');
        Schema::table('messages', fn (Blueprint $table) => $table->dropColumn(['kind', 'mentions']));
    }
};
