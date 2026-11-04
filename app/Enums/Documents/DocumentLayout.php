<?php

declare(strict_types=1);

namespace App\Enums\Documents;

use App\Interfaces\Shared\HasLabel;
use App\Traits\Shared\EnumHelpers;

/**
 * Page layouts of generated PDFs (not to be confused with DocumentTemplate, the wording doctors write from), all on the shared letterhead (layouts.paper): one record as a document (sick
 * leave, result, invoice…) or a table of rows (list exports, financial reports).
 */
enum DocumentLayout: string implements HasLabel
{
    use EnumHelpers;

    case Document = 'document';
    case Table = 'table';

    /** Blade view under resources/views. */
    public function view(): string
    {
        return match ($this) {
            self::Document => 'pdf.document',
            self::Table => 'pdf.table',
        };
    }
}
