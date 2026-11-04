<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Muting a chat, per member: `muted_at` set = muted; `muted_until` null = until unmuted, else it ends by itself.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestampTz('muted_at')->nullable()->after('left_at');
            $table->timestampTz('muted_until')->nullable()->after('muted_at');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn(['muted_at', 'muted_until']);
        });
    }
};
