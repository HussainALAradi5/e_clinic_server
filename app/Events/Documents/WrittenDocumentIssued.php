<?php

declare(strict_types=1);

namespace App\Events\Documents;

use App\Models\Documents\WrittenDocument;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A document written at a visit was issued (a medical report, a certificate …); billing charges it when its kind
 * costs money.
 */
final class WrittenDocumentIssued
{
    use Dispatchable;

    public function __construct(public readonly WrittenDocument $document) {}
}
