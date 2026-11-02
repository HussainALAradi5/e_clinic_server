<?php

use App\Enums\Appointments\RoomType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Appointments › Rooms: physical rooms (consultation, physiotherapy, X-ray …).
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->string('name', 100);
            $table->string('name_ar', 100)->nullable();
            $table->string('type', 20)->index();
            $table->foreignUuid('department_id')->nullable()->index()->constrained('departments')->nullOnDelete();
            $table->string('floor', 10)->nullable();
            $table->unsignedSmallInteger('capacity')->default(1);
            $table->boolean('is_in_service')->default(true);
            $table->string('out_of_service_reason', 255)->nullable();
            $table->text('notes')->nullable();
            $table->index('deleted_by');
            $table->index('created_by');
            $table->index('updated_by');
        });

        Constraints::check('rooms', 'chk_rooms_capacity', 'capacity >= 1');
        Constraints::enum('rooms', 'type', RoomType::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
