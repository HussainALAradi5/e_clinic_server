<?php

declare(strict_types=1);

namespace App\Utilities\Shared;

use App\Interfaces\Shared\HasLabel;
use BackedEnum;
use Illuminate\Support\Facades\Config;

/**
 * Builds the GET /meta/enums payload from config('clinic.enums'): options (value + label) and, for state enums,
 * the transitions map — the client never hard-codes labels or allowed status changes.
 */
final class EnumCatalog
{
    /**
     * @return array<string, array{options: list<array{value: string|int, label: string}>, transitions?: array<string, list<string|int>>}>
     */
    public function all(): array
    {
        $catalog = [];

        foreach (Config::array('clinic.enums') as $enum) {
            if (! is_string($enum) || ! is_subclass_of($enum, BackedEnum::class) || ! is_subclass_of($enum, HasLabel::class)) {
                continue;
            }

            $entry = ['options' => array_map(
                fn (BackedEnum&HasLabel $case): array => ['value' => $case->value, 'label' => $case->label()],
                $enum::cases(),
            )];

            if (method_exists($enum, 'transitions')) {
                $entry['transitions'] = [];
                foreach ($enum::transitions() as $from => $targets) {
                    $entry['transitions'][(string) $from] = array_values(array_map(fn (BackedEnum $to): string|int => $to->value, $targets));
                }
            }

            $catalog[class_basename($enum)] = $entry;
        }

        return $catalog;
    }
}
