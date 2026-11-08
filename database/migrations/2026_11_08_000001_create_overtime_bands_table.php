<?php

declare(strict_types=1);

use App\Enums\HR\SettingKey;
use App\Models\HR\OvertimeBand;
use App\Models\HR\Setting;
use App\Utilities\Database\Constraints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR › Overtime pay by time of day (OTB-000001): a band runs from start_minute to end_minute of the clinic day, in
 * minutes after midnight, at its own pay per hour. Overtime outside every band is paid at the hourly wage x the
 * overtime multiplier. A pay per hour already saved on the overtime setting becomes one all-day band, so pay is unchanged.
 */
return new class extends Migration
{
    private const LAST_MINUTE_OF_DAY = 1439;

    public function up(): void
    {
        Schema::create('overtime_bands', function (Blueprint $table) {
            $table->genericEntity(20);
            $table->blameable();
            $table->unsignedSmallInteger('start_minute');
            $table->unsignedSmallInteger('end_minute');
            $table->money('rate');
            $table->index('start_minute');
        });
        Constraints::check('overtime_bands', 'chk_overtime_bands_range', 'start_minute < end_minute AND end_minute <= 1440');
        Constraints::check('overtime_bands', 'chk_overtime_bands_rate', 'rate >= 0');

        $overtime = Setting::query()->where('key', SettingKey::Overtime->value)->first();
        if ($overtime?->amount !== null) {
            OvertimeBand::query()->create(['start_minute' => 0, 'end_minute' => self::LAST_MINUTE_OF_DAY, 'rate' => $overtime->amount]);
            $overtime->forceFill(['amount' => null])->save();
        }
    }

    public function down(): void
    {
        $allDay = OvertimeBand::query()->where('start_minute', 0)->where('end_minute', self::LAST_MINUTE_OF_DAY)->first();
        if ($allDay !== null) {
            Setting::query()->where('key', SettingKey::Overtime->value)->first()?->forceFill(['amount' => $allDay->rate])->save();
        }

        Schema::dropIfExists('overtime_bands');
    }
};
