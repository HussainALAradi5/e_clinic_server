<?php

declare(strict_types=1);

namespace App\Interfaces\Shared;

/**
 * Documents that receive a gap-free number (invoice, payment, refund, journal entry, expense …).
 */
interface HasDocumentNumber
{
    /** Key in document_sequences, e.g. 'invoice'. */
    public static function documentType(): string;

    /** Printed prefix, e.g. 'INV'. */
    public static function documentPrefix(): string;

    public function assignNumber(string $number): void;
}
