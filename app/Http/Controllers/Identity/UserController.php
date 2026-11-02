<?php

declare(strict_types=1);

namespace App\Http\Controllers\Identity;

use App\Http\Concerns\ApiResponse;
use App\Http\Requests\Identity\ChangeUserStatusRequest;
use App\Http\Requests\Identity\FindDuplicatesRequest;
use App\Http\Requests\Identity\UpdatePersonRequest;
use App\Http\Resources\Identity\PersonResource;
use App\Http\Resources\Identity\UserResource;
use App\Interfaces\Identity\UserServiceInterface;
use App\Models\Identity\Person;
use App\Models\Identity\User;
use App\Utilities\Access\OwnershipScope;
use App\Utilities\Query\ListQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UserController
{
    use ApiResponse;

    public function __construct(private readonly UserServiceInterface $users) {}

    public function searchPeople(Request $request): JsonResponse
    {
        $request->validate(['search' => ['nullable', 'string', 'min:2', 'max:100'], 'national_id' => ['nullable', 'string', 'max:30']]);
        $query = ListQuery::fromRequest($request, Person::listQueryConfig());

        return $this->paginated(PersonResource::collection($this->users->searchPeople($query, $request->string('national_id')->toString() ?: null)), $query);
    }

    public function duplicates(FindDuplicatesRequest $request): JsonResponse
    {
        $candidates = $this->users->findDuplicates($request->toData(), $request->validated('except_id'));

        return $this->ok(PersonResource::collection($candidates));
    }

    public function updatePerson(UpdatePersonRequest $request, string $person): JsonResponse
    {
        $model = OwnershipScope::apply(Person::query(), 'people', $request->user())->findOrFail($person);

        return $this->ok(new PersonResource($this->users->updatePerson($model, $request->toData(), $request->integer('version'))->load('user')));
    }

    public function index(Request $request): JsonResponse
    {
        $request->validate(['role' => ['nullable', 'string', 'max:100'], 'without_role' => ['nullable', 'string', 'max:100']]);
        $query = ListQuery::fromRequest($request, User::listQueryConfig());
        $users = $this->users->list($query, $request->string('role')->toString() ?: null, $request->string('without_role')->toString() ?: null);

        return $this->paginated(UserResource::collection($users), $query);
    }

    public function show(string $user): JsonResponse
    {
        return $this->ok((new UserResource($this->users->find($user)))->withPerson());
    }

    public function changeStatus(ChangeUserStatusRequest $request, string $user): JsonResponse
    {
        $updated = $this->users->changeStatus($this->users->find($user), $request->status(), $request->validated('reason'));

        return $this->ok((new UserResource($updated))->withPerson());
    }

    public function sendResetLink(string $user): JsonResponse
    {
        $this->users->sendPasswordResetLink($this->users->find($user));

        return $this->accepted(__('passwords.sent'));
    }
}
