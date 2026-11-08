<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Dashboards\AttendanceMetric;
use App\Enums\Dashboards\DashboardSection;
use App\Enums\Dashboards\DashboardWidgetSize;
use App\Models\Dashboards\Dashboard;
use Illuminate\Database\Seeder;

/**
 * The default dashboard of each section, for every environment. Idempotent: once it exists (even changed or deleted by
 * the clinic) it is left as it is, and it only becomes the default when the section has none.
 */
final class DashboardSeeder extends Seeder
{
    public const ATTENDANCE_CODE = 'DSH-ATTENDANCE';

    public function run(): void
    {
        Dashboard::query()->withDeleted()->firstOrCreate(['code' => self::ATTENDANCE_CODE], [
            'name' => 'Attendance overview',
            'description' => 'Who is in today, who is late or away, and overtime waiting for a decision.',
            'section' => DashboardSection::Attendance,
            'is_default' => Dashboard::query()->where('section', DashboardSection::Attendance->value)->where('is_default', true)->doesntExist(),
            'widgets' => [
                $this->widget(AttendanceMetric::Present, DashboardWidgetSize::Small, 0),
                $this->widget(AttendanceMetric::Late, DashboardWidgetSize::Small, 1),
                $this->widget(AttendanceMetric::Absent, DashboardWidgetSize::Small, 2),
                $this->widget(AttendanceMetric::OnLeave, DashboardWidgetSize::Small, 3),
                $this->widget(AttendanceMetric::PendingOvertime, DashboardWidgetSize::Small, 4),
                $this->widget(AttendanceMetric::ApprovedOvertimeMinutes, DashboardWidgetSize::Small, 5),
                $this->widget(AttendanceMetric::NeedsReview, DashboardWidgetSize::Small, 6),
                $this->widget(AttendanceMetric::LateArrivals, DashboardWidgetSize::Large, 7),
            ],
        ]);
    }

    /**
     * @return array{metric: string, title: null, size: string, position: int}
     */
    private function widget(AttendanceMetric $metric, DashboardWidgetSize $size, int $position): array
    {
        return ['metric' => $metric->value, 'title' => null, 'size' => $size->value, 'position' => $position];
    }
}
