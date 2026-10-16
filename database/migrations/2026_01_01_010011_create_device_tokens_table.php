<?php

use App\Enums\Identity\DevicePlatform;
use App\Enums\Identity\MobileApp;
use App\Shared\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Notifications: push targets of the Flutter apps (FCM).
     */
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->index()->constrained('users')->restrictOnDelete();
            $table->string('app', 10);
            $table->string('platform', 10);
            $table->string('token')->unique();
            $table->string('device_name', 100)->nullable();
            $table->string('app_version', 20)->nullable();
            $table->timestampTz('last_seen_at')->useCurrent();
            $table->timestampsTz();
        });

        Constraints::enum('device_tokens', 'app', MobileApp::class);
        Constraints::enum('device_tokens', 'platform', DevicePlatform::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
