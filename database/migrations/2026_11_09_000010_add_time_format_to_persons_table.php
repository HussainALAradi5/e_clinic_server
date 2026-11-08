<?php

declare(strict_types=1);

use App\Enums\Identity\TimeFormat;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each person's choice of 12-hour (the default) or 24-hour times. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('persons', function (Blueprint $table): void {
            $table->string('time_format', 3)->default(TimeFormat::TwelveHour->value);
        });
        Constraints::enum('persons', 'time_format', TimeFormat::class);
    }

    public function down(): void
    {
        Constraints::statement('ALTER TABLE persons DROP CONSTRAINT IF EXISTS chk_persons_time_format');
        Schema::table('persons', function (Blueprint $table): void {
            $table->dropColumn('time_format');
        });
    }
};
