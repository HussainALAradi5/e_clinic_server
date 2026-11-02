<?php

use App\Enums\Identity\UserStatus;
use App\Enums\Identity\UserType;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identity › Users: login account of a person — same id as persons.id (shared primary key).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('password')->nullable();
            $table->string('user_type', 20);
            $table->string('status', 30)->default(UserStatus::Active->value);
            $table->timestampTz('email_verified_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestampTz('two_factor_confirmed_at')->nullable();
            $table->timestampTz('last_login_at')->nullable();
            $table->ipAddress('last_login_ip')->nullable();
            $table->timestampTz('locked_at')->nullable();

            $table->foreign('id')->references('id')->on('persons')->restrictOnDelete();
            $table->index(['status', 'user_type']);
        });

        Constraints::enum('users', 'user_type', UserType::class);
        Constraints::enum('users', 'status', UserStatus::class);
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
