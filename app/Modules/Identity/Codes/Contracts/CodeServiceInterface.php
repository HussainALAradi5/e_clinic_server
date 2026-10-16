<?php

declare(strict_types=1);

namespace App\Modules\Identity\Codes\Contracts;

use App\Enums\Identity\CodeSymbology;
use App\Enums\Identity\ScanPurpose;
use App\Modules\Identity\Codes\Data\CodeImage;
use App\Modules\Identity\Codes\Data\ScanResultData;

interface CodeServiceInterface
{
    /** SVG / PNG of a record's signed code (caller needs read access to the record; cached 24 h). */
    public function image(string $entityAlias, string $id, CodeSymbology $symbology, int $size = 256, string $format = 'svg'): CodeImage;

    /** Signed payload `<alias>:<code>.<signature>` of a record. */
    public function payloadFor(string $entityAlias, string $code): string;

    /** Resolves a scanned payload for a signed-in user (their access rules apply → 404). */
    public function resolve(string $payload, ScanPurpose $purpose): ScanResultData;

    /** Public authenticity check: only entities with a `public` summary; never sensitive data. */
    public function verify(string $payload): ScanResultData;
}
