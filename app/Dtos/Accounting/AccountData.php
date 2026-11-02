<?php

declare(strict_types=1);

namespace App\Dtos\Accounting;

use Illuminate\Support\Arr;

/**
 * Account input; on update only the provided keys change (`parent_id: null` moves it to the top level).
 */
final readonly class AccountData
{
    private const FIELDS = ['code', 'name', 'name_ar', 'type', 'parent_id', 'is_group', 'description'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(public array $values) {}

    /**
     * @param  array<string, mixed>  $input  validated snake_case input
     */
    public static function fromArray(array $input): self
    {
        return new self(array_map(fn (mixed $v): mixed => $v === '' ? null : $v, Arr::only($input, self::FIELDS)));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}
