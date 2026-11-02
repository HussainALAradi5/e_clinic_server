<?php

declare(strict_types=1);

namespace App\Interfaces\Payments;

use App\Dtos\Payments\RefundRequestData;
use App\Models\Payments\Refund;
use App\Utilities\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface RefundServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, Refund>|CursorPaginator<int, Refund>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Refund with payment and invoice. */
    public function find(string $id): Refund;

    /** Requests a refund of (part of) a completed payment; never more than what is left to refund. */
    public function request(RefundRequestData $data): Refund;

    /** `pending → approved` by someone other than the requester. */
    public function approve(Refund $refund): Refund;

    /** `pending → rejected` by someone other than the requester. */
    public function reject(Refund $refund, string $reason): Refund;

    /** Pays an approved refund out: Dr sales returns / Cr cash or bank; cash needs an open drawer. */
    public function process(Refund $refund, ?string $referenceNumber): Refund;
}
