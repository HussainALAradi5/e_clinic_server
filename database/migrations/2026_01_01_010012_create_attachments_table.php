<?php

use App\Enums\Identity\AttachmentCategory;
use App\Enums\Identity\FileScanStatus;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Attachments: files attached to any record (polymorphic), stored privately.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->genericEntity();
            $table->blameable();
            $table->string('attachable_type', 50);
            $table->uuid('attachable_id');
            $table->string('category', 30);
            $table->string('original_name');
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->string('disk', 30)->default('local');
            $table->string('path', 500);
            $table->char('sha256', 64);
            $table->string('scan_status', 10)->default(FileScanStatus::Pending->value);
            $table->boolean('is_sensitive')->default(false);
            $table->string('description')->nullable();

            $table->index(['attachable_type', 'attachable_id']);
            $table->index('sha256');
            $table->index('created_by');
        });

        Constraints::enum('attachments', 'category', AttachmentCategory::class);
        Constraints::enum('attachments', 'scan_status', FileScanStatus::class);
        Constraints::check('attachments', 'chk_attachments_size', 'size_bytes > 0 AND size_bytes <= 524288000');
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
