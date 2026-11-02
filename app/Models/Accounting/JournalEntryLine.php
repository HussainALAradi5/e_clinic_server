<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Appointments\Doctor;
use App\Models\Appointments\Patient;
use App\Models\HR\Department;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Debit or credit line (exactly one side non-zero) with optional cost centre and patient / doctor sub-ledger.
 *
 * @property string $id
 * @property string $journal_entry_id
 * @property int $line_number
 * @property string $account_id
 * @property string $debit
 * @property string $credit
 * @property ?string $description
 * @property ?string $department_id
 * @property ?string $patient_id
 * @property ?string $doctor_id
 * @property-read Account $account
 * @property-read ?Department $department
 * @property-read ?Patient $patient
 * @property-read ?Doctor $doctor
 * @property-read JournalEntry $entry
 */
class JournalEntryLine extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = ['id'];

    /**
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withDeleted();
    }

    /**
     * @return BelongsTo<JournalEntry, $this>
     */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class)->withDeleted();
    }

    /**
     * @return BelongsTo<Patient, $this>
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * @return BelongsTo<Doctor, $this>
     */
    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    protected function casts(): array
    {
        return ['line_number' => 'integer', 'debit' => 'decimal:3', 'credit' => 'decimal:3'];
    }
}
