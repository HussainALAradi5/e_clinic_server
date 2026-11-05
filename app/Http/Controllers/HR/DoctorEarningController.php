<?php

declare(strict_types=1);

namespace App\Http\Controllers\HR;

use App\Dtos\HR\DoctorEarning;
use App\Enums\HR\CommissionType;
use App\Http\Concerns\ApiResponse;
use App\Interfaces\Appointments\DoctorServiceInterface;
use App\Models\Appointments\Doctor;
use App\Utilities\HR\DoctorEarnings;
use App\Utilities\Shared\MoneyHelper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Doctor earnings: commission terms (salary data — employees.manage_salary) and the month's earnings report
 * (payroll readers see every doctor; a doctor sees only their own row).
 */
final class DoctorEarningController
{
    use ApiResponse;

    public function __construct(private readonly DoctorServiceInterface $doctors) {}

    /** `?year=&month=` (default: last month): revenue, completed visits and commission per doctor. */
    public function index(Request $request): JsonResponse
    {
        $request->validate(['year' => ['nullable', 'integer', 'between:2020,2100'], 'month' => ['nullable', 'integer', 'between:1,12']]);
        $default = CarbonImmutable::now()->subMonthNoOverflow();
        $from = $default->setDate($request->integer('year', $default->year), $request->integer('month', $default->month), 1)->startOfMonth();
        $to = $from->endOfMonth();
        $query = Doctor::query()->with('person');
        if (! ($request->user()?->can('payroll.read_all') ?? false)) {
            $query->whereKey($request->user()?->getAuthIdentifier());
        }
        $rows = $query->get()->map(fn (Doctor $d): array => $this->row(DoctorEarnings::of($d, $from, $to)))->values();

        return $this->ok($rows, meta: ['from' => $from->toDateString(), 'to' => $to->toDateString()]);
    }

    /** Sets a doctor's commission: none, percent (0–100) or per_visit (BHD per completed visit). */
    public function update(Request $request, string $doctor): JsonResponse
    {
        $request->validate([
            'commission_type' => ['required', Rule::enum(CommissionType::class)],
            'commission_value' => ['required_unless:commission_type,none', 'nullable', 'decimal:0,3', 'gte:0', Rule::when($request->input('commission_type') === 'percent', ['lte:100'], ['lte:1000'])],
        ]);
        $record = $this->doctors->find($doctor);
        $type = CommissionType::from($request->string('commission_type')->toString());
        $record->forceFill(['commission_type' => $type, 'commission_value' => $type === CommissionType::None ? '0' : (string) $request->input('commission_value')])->save();

        return $this->ok(['commission_type' => $record->commission_type->value, 'commission_value' => (string) $record->commission_value]);
    }

    /** @return array<string, mixed> */
    private function row(DoctorEarning $e): array
    {
        return [
            'doctor' => ['id' => $e->doctorId, 'full_name' => $e->doctorName],
            'commission_type' => $e->type->value,
            'rate' => $e->rate,
            'revenue' => MoneyHelper::toApi($e->revenue),
            'visits' => $e->visits,
            'commission' => MoneyHelper::toApi($e->commission),
        ];
    }
}
