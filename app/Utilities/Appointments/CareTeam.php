<?php

declare(strict_types=1);

namespace App\Utilities\Appointments;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Who is "my patient" — one rule for every clinical record a user with read_own may see (patient file, visits and
 * their notes, orders and results, prescriptions, treatments, consents, medicines given). A clinician cares for a
 * patient they have seen or booked (any visit or session with them as provider), whose treatment plan they lead,
 * or whose file they opened in an emergency (an active break-the-glass grant). Their history then includes what
 * colleagues recorded, as in a real practice; writing stays with whoever owns each record.
 */
final class CareTeam
{
    /** Patient ids in the user's care, as a sub-query for `whereIn`. */
    public static function patientIds(Authenticatable $user): Builder
    {
        $id = (string) $user->getAuthIdentifier();

        return DB::query()->fromSub(
            DB::table('appointments')->select('patient_id')->where('provider_id', $id)
                ->union(DB::table('treatments')->select('patient_id')->where('doctor_id', $id))
                ->union(DB::table('patient_access_grants')->select('patient_id')->where('user_id', $id)->where('expires_at', '>', now())),
            'care',
        )->select('care.patient_id');
    }
}
