<?php

declare(strict_types=1);

namespace App\Modules\Identity\Users\Http\Controllers;

use App\Modules\Identity\Users\Contracts\UserServiceInterface;
use App\Modules\Identity\Users\Http\Requests\ChangeUserStatusRequest;
use App\Modules\Identity\Users\Http\Requests\FindDuplicatesRequest;
use App\Modules\Identity\Users\Http\Requests\UpdatePersonRequest;
use App\Modules\Identity\Users\Http\Resources\PersonResource;
use App\Modules\Identity\Users\Http\Resources\UserResource;
use App\Modules\Identity\Users\Models\Person;
use App\Modules\Identity\Users\Models\User;
use App\Shared\Access\OwnershipScope;
use App\Shared\Http\Concerns\ApiResponse;
use App\Shared\Query\ListQuery;
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
        $request->validate(['role' => ['nullable', 'string', 'max:100']]);
        $query = ListQuery::fromRequest($request, User::listQueryConfig());

        return $this->paginated(UserResource::collection($this->users->list($query, $request->string('role')->toString() ?: null)), $query);
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
