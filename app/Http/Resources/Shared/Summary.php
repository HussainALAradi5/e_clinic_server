<?php

declare(strict_types=1);

namespace App\Http\Resources\Shared;

use App\Models\Appointments\Patient;
use App\Models\HR\Employee;
use App\Models\Identity\User;

/**
 * Compact embedded shapes shared by resources (a patient line, a staff name), so every list renders them alike.
 */
final class Summary
{
    /**
     * @return array{id: string, code: string, full_name: string, phone: ?string}
     */
    public static function patient(Patient $patient): array
    {
        return [
            'id' => $patient->id,
            'code' => $patient->person->code,
            'full_name' => $patient->person->full_name,
            'phone' => $patient->person->phone,
        ];
    }

    /**
     * @return ?array{id: string, full_name: string}
     */
    public static function user(?User $user): ?array
    {
        return $user === null ? null : ['id' => $user->id, 'full_name' => $user->person->full_name];
    }

    /**
     * Staff member line: name, file code, job title and department (when loaded).
     *
     * @return array{id: string, code: string, full_name: string, job_title: string, department: ?array{id: string, name: string}}
     */
    public static function employee(Employee $employee): array
    {
        return [
            'id' => $employee->id,
            'code' => $employee->person->code,
            'full_name' => $employee->person->full_name,
            'job_title' => $employee->job_title,
            'department' => $employee->relationLoaded('department') ? ['id' => $employee->department->id, 'name' => $employee->department->name] : null,
        ];
    }
}
