<?php

declare(strict_types=1);

use App\Enums\Appointments\RoomType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;

/**
 * More kinds of room: ultrasound, dental, procedure, vaccination and the pharmacy (PostgreSQL check refreshed).
 */
return new class extends Migration
{
    public function up(): void
    {
        Constraints::statement('ALTER TABLE rooms DROP CONSTRAINT IF EXISTS chk_rooms_type');
        Constraints::enum('rooms', 'type', RoomType::class);
    }

    public function down(): void
    {
        // The wider check stays: narrowing it would fail on rooms of the new kinds.
    }
};
