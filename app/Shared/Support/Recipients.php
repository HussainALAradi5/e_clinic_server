<?php

declare(strict_types=1);

namespace App\Shared\Support;

use App\Enums\Identity\UserStatus;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use Illuminate\Support\Collection;

/**
 * Who should hear about a workflow step: the active staff holding a right (e.g. `refunds.approve`), never the
 * person who triggered it.
 */
final class Recipients
{
    /**
     * @param  list<?string>  $exceptUserIds
     * @return Collection<int, Person>
     */
    public static function withPermission(string $permission, array $exceptUserIds = []): Collection
    {
        /** @var Collection<int, Person> $people */
        $people = User::permission($permission)
            ->where('status', UserStatus::Active)
            ->whereNotIn('id', array_filter($exceptUserIds))
            ->with('person')
            ->get()
            // Senders read `$person->user` next: hand back the loaded account instead of one query per person.
            ->map(fn (User $user): Person => $user->person->setRelation('user', $user))
            ->values();

        return $people;
    }

    /**
     * @return Collection<int, Person>
     */
    public static function user(?string $userId): Collection
    {
        $person = $userId === null ? null : User::query()->with('person')->find($userId)?->person;

        return collect($person === null ? [] : [$person]);
    }
}
