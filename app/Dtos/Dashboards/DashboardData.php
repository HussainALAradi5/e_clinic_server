<?php

declare(strict_types=1);

namespace App\Dtos\Dashboards;

use Illuminate\Support\Arr;

/** Dashboard input; on update only the provided keys change. Widgets are stored in display order. */
final readonly class DashboardData
{
    private const FIELDS = ['name', 'description', 'section', 'is_default', 'widgets'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        $values = Arr::only($input, self::FIELDS);
        if (isset($values['widgets']) && is_array($values['widgets'])) {
            $values['widgets'] = self::widgets($values['widgets']);
        }

        return new self($values);
    }

    /**
     * @param  array<int|string, mixed>  $widgets  validated rows
     * @return list<array{metric: mixed, title: mixed, size: mixed, position: int}>
     */
    private static function widgets(array $widgets): array
    {
        $rows = [];
        foreach ($widgets as $widget) {
            /** @var array{metric: mixed, title?: mixed, size: mixed, position: int|string} $widget */
            $rows[] = ['metric' => $widget['metric'], 'title' => $widget['title'] ?? null, 'size' => $widget['size'], 'position' => (int) $widget['position']];
        }
        usort($rows, fn (array $a, array $b): int => $a['position'] <=> $b['position']);

        return $rows;
    }
}
