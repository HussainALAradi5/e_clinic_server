<?php

declare(strict_types=1);

namespace App\Modules\HR\Leaves\Data;

/**
 * Validated leave type fields (code, name, quota, paid, attachment, carry forward, notice).
 */
final readonly class LeaveTypeData
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(public array $values) {}
}
