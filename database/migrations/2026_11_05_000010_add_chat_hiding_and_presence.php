<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - Deleting a chat for myself (soft): `hidden_at` takes it off my list, `cleared_at` hides the messages before it
 *   (a one-to-one chat comes back, from then on, when the other person writes again).
 * - My Online / Offline status: set by signing in and out, switched by hand.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->timestampTz('hidden_at')->nullable()->after('muted_until');
            $table->timestampTz('cleared_at')->nullable()->after('hidden_at');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->string('presence', 10)->default('offline')->after('status');
            $table->timestampTz('presence_at')->nullable()->after('presence');
        });
    }

    public function down(): void
    {
        Schema::table('conversation_participants', function (Blueprint $table): void {
            $table->dropColumn(['hidden_at', 'cleared_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['presence', 'presence_at']);
        });
    }
};
