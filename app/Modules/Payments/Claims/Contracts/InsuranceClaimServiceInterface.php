<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Contracts;

use App\Modules\Payments\Claims\Data\ClaimDecisionData;
use App\Modules\Payments\Claims\Data\RemittanceData;
use App\Modules\Payments\Claims\Models\InsuranceClaim;
use App\Modules\Payments\Invoices\Models\Invoice;
use App\Shared\Query\ListQuery;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface InsuranceClaimServiceInterface
{
    /**
     * @return LengthAwarePaginator<int, InsuranceClaim>|CursorPaginator<int, InsuranceClaim>
     */
    public function list(ListQuery $query): LengthAwarePaginator|CursorPaginator;

    /** Claim with invoice lines, insurer and payment. */
    public function find(string $id): InsuranceClaim;

    /** Draft claim of the insurer's share of an issued invoice (policy valid, signed diagnoses, none open). */
    public function createForInvoice(Invoice $invoice, ?string $preAuthorizationNumber): InsuranceClaim;

    /** `draft → pending`: sent to the insurer. */
    public function submit(InsuranceClaim $claim): InsuranceClaim;

    /** `pending → approved` (records the insurance payment on the invoice) or `→ rejected`. */
    public function decide(InsuranceClaim $claim, ClaimDecisionData $data): InsuranceClaim;

    /**
     * Insurer's bank transfer for approved claims of one insurer: Dr bank / Cr insurance receivable.
     *
     * @return Collection<int, InsuranceClaim>
     */
    public function recordRemittance(RemittanceData $data): Collection;

    /** `draft | pending → cancelled`. */
    public function cancel(InsuranceClaim $claim, string $reason): InsuranceClaim;

    /**
     * Primary and secondary ICD-10 codes of the visits on the claim (for the claim form).
     *
     * @return list<array{code: string, title: string, is_primary: bool, visit: string}>
     */
    public function diagnoses(InsuranceClaim $claim): array;
}
