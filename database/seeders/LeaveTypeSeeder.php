<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\HR\Leaves\Models\LeaveType;
use Illuminate\Database\Seeder;

/**
 * Default leave types (Bahrain private sector). Idempotent: existing codes are left as HR edited them.
 */
final class LeaveTypeSeeder extends Seeder
{
    /** code => [name, Arabic name, yearly days, paid, certificate, carry forward, notice days] */
    private const TYPES = [
        'ANNUAL' => ['Annual leave', 'إجازة سنوية', 30, true, false, 10, 7],
        'SICK' => ['Sick leave', 'إجازة مرضية', 15, true, true, 0, 0],
        'EMERGENCY' => ['Emergency leave', 'إجازة طارئة', 3, true, false, 0, 0],
        'MATERNITY' => ['Maternity leave', 'إجازة أمومة', 60, true, true, 0, 30],
        'UNPAID' => ['Unpaid leave', 'إجازة بدون راتب', 0, false, false, 0, 3],
    ];

    public function run(): void
    {
        foreach (self::TYPES as $code => [$name, $nameAr, $days, $paid, $certificate, $carry, $notice]) {
            LeaveType::query()->withDeleted()->firstOrCreate(['code' => $code], [
                'name' => $name, 'name_ar' => $nameAr, 'annual_quota_days' => $days, 'is_paid' => $paid,
                'requires_attachment' => $certificate, 'max_carry_forward_days' => $carry, 'min_notice_days' => $notice,
            ]);
        }
    }
}
