<?php

use App\Enums\Identity\LoginFailureReason;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Auth: every login attempt (lockout logic, security review). Pruned after 180 days.
     */
    public function up(): void
    {
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->ipAddress('ip_address');
            $table->string('user_agent', 512)->nullable();
            $table->boolean('successful');
            $table->string('failure_reason', 30)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['email', 'created_at']);
            $table->index(['ip_address', 'created_at']);
        });

        Constraints::enum('login_attempts', 'failure_reason', LoginFailureReason::class, nullable: true);
    }

    public function down(): void
    {
        Schema::dropIfExists('login_attempts');
    }
};
