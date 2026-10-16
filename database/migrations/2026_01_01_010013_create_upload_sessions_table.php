<?php

use App\Enums\Identity\AttachmentCategory;
use App\Shared\Database\Constraints;
use App\Shared\Enums\GenericStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Attachments: resumable (chunked) uploads in progress.
     */
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->string('category', 30);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('chunk_size')->default(5 * 1024 * 1024);
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->char('sha256', 64)->nullable();
            $table->string('temp_path', 500);
            $table->string('status', 20)->default(GenericStatus::Pending->value);
            $table->foreignUuid('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->index(['user_id', 'status']);
            $table->index('expires_at');
        });

        Constraints::enum('upload_sessions', 'category', AttachmentCategory::class);
        Constraints::enum('upload_sessions', 'status', GenericStatus::class);
        Constraints::check('upload_sessions', 'chk_upload_sessions_bytes', 'received_bytes <= size_bytes');
    }

    public function down(): void
    {
        Schema::dropIfExists('upload_sessions');
    }
};
