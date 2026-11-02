<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Dtos\Identity\SavedViewData;
use App\Exceptions\Identity\SavedViewNameTakenException;
use App\Exceptions\Shared\UnknownResourceException;
use App\Interfaces\Identity\ListViewServiceInterface;
use App\Models\Identity\SavedView;
use App\Models\Identity\User;
use App\Services\Bulk\BulkActionRegistry;
use App\Utilities\Access\PermissionExpression;
use App\Utilities\Query\ApplyListQuery;
use App\Utilities\Query\ListQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ListViewService implements ListViewServiceInterface
{
    public function __construct(
        private readonly ApplyListQuery $listQuery,
        private readonly BulkActionRegistry $registry,
    ) {}

    public function list(ListQuery $query, ?string $resource = null): LengthAwarePaginator|CursorPaginator
    {
        $user = $this->user();
        $readable = array_keys(array_filter($this->registry->all(), fn ($definition): bool => PermissionExpression::parse($definition->readPermission())->evaluate($user)));

        $builder = SavedView::query()
            ->where(fn (Builder $q) => $q->where('user_id', $user->id)->orWhere(fn (Builder $s) => $s->where('is_shared', true)->whereIn('resource', $readable)))
            ->when($resource !== null, fn (Builder $q) => $q->where('resource', $resource));

        return $this->listQuery->paginate($builder, $query);
    }

    public function find(string $id): SavedView
    {
        $view = SavedView::query()->findOrFail($id);
        $user = $this->user();
        $readable = $this->registry->has($view->resource) && PermissionExpression::parse($this->registry->for($view->resource)->readPermission())->evaluate($user);
        if (! $view->isOwnedBy($user) && ! ($view->is_shared && $readable)) {
            throw (new ModelNotFoundException)->setModel(SavedView::class);
        }

        return $view;
    }

    public function create(SavedViewData $data): SavedView
    {
        $resource = (string) $data->resource;
        $this->validate($resource, $data);
        $user = $this->user();

        return DB::transaction(function () use ($data, $resource, $user): SavedView {
            $this->assertNameFree($user, $resource, (string) $data->name);
            if ($data->isDefault === true) {
                $this->clearDefault($user, $resource);
            }

            return SavedView::query()->create([
                'user_id' => $user->id,
                'resource' => $resource,
                'name' => $data->name,
                'query' => $data->query ?? [],
                'columns' => $data->columns ?? [],
                'is_shared' => $data->isShared ?? false,
                'is_default' => $data->isDefault ?? false,
            ]);
        });
    }

    public function update(SavedView $view, SavedViewData $data): SavedView
    {
        $this->assertOwner($view);
        $this->validate($view->resource, $data);

        return DB::transaction(function () use ($view, $data): SavedView {
            if (in_array('name', $data->provided, true) && $data->name !== $view->name) {
                $this->assertNameFree($this->user(), $view->resource, (string) $data->name);
            }
            $view->name = $data->name ?? $view->name;
            $view->query = $data->query ?? $view->query;
            $view->columns = $data->columns ?? $view->columns;
            $view->is_shared = $data->isShared ?? $view->is_shared;
            $view->save();

            return $view;
        });
    }

    public function setDefault(SavedView $view): SavedView
    {
        $this->assertOwner($view);

        return DB::transaction(function () use ($view): SavedView {
            $this->clearDefault($this->user(), $view->resource);
            $view->forceFill(['is_default' => true])->save();

            return $view;
        });
    }

    public function delete(SavedView $view): void
    {
        $this->assertOwner($view);
        $view->delete();
    }

    private function validate(string $resource, SavedViewData $data): void
    {
        if (! $this->registry->has($resource)) {
            throw new UnknownResourceException;
        }
        if ($data->query !== null) {
            ListQuery::fromArray($data->query, $this->registry->for($resource)->config);
        }
        if ($data->isShared === true && ! $this->user()->can("{$resource}.read_all")) {
            throw ValidationException::withMessages(['is_shared' => [__('errors.FORBIDDEN')]]);
        }
    }

    private function assertNameFree(User $user, string $resource, string $name): void
    {
        if (SavedView::query()->where(['user_id' => $user->id, 'resource' => $resource, 'name' => $name])->exists()) {
            throw new SavedViewNameTakenException;
        }
    }

    private function clearDefault(User $user, string $resource): void
    {
        SavedView::query()->where(['user_id' => $user->id, 'resource' => $resource, 'is_default' => true])->update(['is_default' => false]);
    }

    private function assertOwner(SavedView $view): void
    {
        if (! $view->isOwnedBy($this->user())) {
            throw new AuthorizationException;
        }
    }

    private function user(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return $user;
    }
}
