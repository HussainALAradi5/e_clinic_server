<?php

declare(strict_types=1);

namespace App\Modules\Identity\Views\Contracts;

use App\Modules\Identity\Views\Data\SavedViewData;
use App\Modules\Identity\Views\Models\SavedView;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ListViewServiceInterface
{
    /**
     * Own views + views shared on resources the user can read (optionally of one resource).
     *
     * @return LengthAwarePaginator<int, SavedView>|CursorPaginator<int, SavedView>
     */
    public function list(ListQuery $query, ?string $resource = null): LengthAwarePaginator|CursorPaginator;

    public function find(string $id): SavedView;

    /** Query validated against the resource's ListQueryConfig; sharing needs `<resource>.read_all`. */
    public function create(SavedViewData $data): SavedView;

    /** Owner only. */
    public function update(SavedView $view, SavedViewData $data): SavedView;

    /** Owner's default for the resource (previous default cleared). */
    public function setDefault(SavedView $view): SavedView;

    /** Hard delete (personal setting). */
    public function delete(SavedView $view): void;
}
