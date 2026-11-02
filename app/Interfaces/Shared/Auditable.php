<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

/**
 * Models whose changes are written to audit_logs (used with the Auditable trait).
 */
interface Auditable
{
    /**
     * Attributes masked as `***` in old / new values, e.g. ['national_id', 'iban'].
     *
     * @return list<string>
     */
    public function auditMasked(): array;

    /**
     * Attributes whose changes alone are not worth an audit row (timestamps, counters …).
     *
     * @return list<string>
     */
    public function auditIgnored(): array;

    /** Module name stored in audit_logs.module, e.g. `identity`. */
    public function auditModule(): string;
}
