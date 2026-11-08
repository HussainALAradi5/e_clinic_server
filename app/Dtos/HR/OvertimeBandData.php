<?php

declare(strict_types=1);

namespace App\Dtos\HR;

/** One overtime time band: minutes after midnight and the pay per hour. */
final readonly class OvertimeBandData
{
    private const MINUTES_PER_HOUR = 60;

    public function __construct(
        public int $startMinute,
        public int $endMinute,
        public string $rate,
    ) {}

    /**
     * @param  array{start: string, end: string, rate: int|float|string}  $input  validated input, times as HH:MM
     */
    public static function fromArray(array $input): self
    {
        return new self(self::minutes($input['start']), self::minutes($input['end']), (string) $input['rate']);
    }

    private static function minutes(string $time): int
    {
        [$hours, $minutes] = array_map('intval', explode(':', $time));

        return $hours * self::MINUTES_PER_HOUR + $minutes;
    }
}
