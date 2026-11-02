<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\BillableServiceData;
use App\Models\Payments\BillableService;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface BillableServiceServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, BillableService>|CursorPaginator<int, BillableService>
     */
    public function listServices(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    public function findService(string $id): BillableService;

    public function findWithDeletedService(string $id): BillableService;

    /**
     * Records using the service (config payments.service_usages), e.g. `['doctors' => 2]`.
     *
     * @return array<string, int>
     */
    public function usage(BillableService $service): array;

    /** Unique code; revenue account must be an active leaf revenue account. */
    public function createService(BillableServiceData $data): BillableService;

    /** Price changes apply to new charges only (issued invoices keep their prices). */
    public function updateService(BillableService $service, BillableServiceData $data): BillableService;

    /** Soft delete; a used service → ServiceInUseException (archive instead). */
    public function deleteService(BillableService $service): void;

    public function archiveService(BillableService $service): BillableService;

    public function unarchiveService(BillableService $service): BillableService;

    public function restoreService(BillableService $service): BillableService;
}
