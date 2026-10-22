<?php

declare(strict_types=1);

namespace App\Modules\Payments\Claims\Data;

/**
 * The insurer's answer: approved (in full or in part, with its reference) or rejected with a reason.
 */
final readonly class ClaimDecisionData
{
    public function __construct(
        public bool $approved,
        public ?string $approvedAmount,
        public ?string $insurerReference,
        public ?string $rejectionReason,
    ) {}
}
