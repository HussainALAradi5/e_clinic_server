<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Single source of "now" and clinic-local dates (Asia/Bahrain). Inject it instead of calling now() so tests can freeze time.
 */
class ClinicClock
{
    public function timezone(): string
    {
        return Config::string('clinic.timezone');
    }

    /** Current instant in UTC. */
    public function now(): CarbonImmutable
    {
        return CarbonImmutable::now('UTC');
    }

    /** Clinic-local date (start of day, clinic time zone). */
    public function today(): CarbonImmutable
    {
        return $this->now()->setTimezone($this->timezone())->startOfDay();
    }

    public function toClinic(CarbonInterface $utc): CarbonImmutable
    {
        return CarbonImmutable::instance($utc)->setTimezone($this->timezone());
    }

    /** Clinic-local date + 'H:i' → UTC instant. */
    public function combine(CarbonInterface $date, string $time): CarbonImmutable
    {
        $local = CarbonImmutable::createFromFormat('Y-m-d H:i', $date->format('Y-m-d').' '.$time, $this->timezone())
            ?? throw new InvalidArgumentException("Invalid time '{$time}', expected H:i.");

        return $local->utc();
    }

    /**
     * Inclusive clinic-local day range as UTC instants: [from 00:00:00, to 23:59:59.999999].
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function dayRangeUtc(CarbonInterface $from, CarbonInterface $to): array
    {
        return [
            CarbonImmutable::parse($from->format('Y-m-d'), $this->timezone())->startOfDay()->utc(),
            CarbonImmutable::parse($to->format('Y-m-d'), $this->timezone())->endOfDay()->utc(),
        ];
    }
}
