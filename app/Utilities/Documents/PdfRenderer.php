<?php

declare(strict_types=1);

namespace App\Utilities\Documents;

use App\Enums\Documents\DocumentLayout;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Config;

/**
 * Renders a page layout to A4 PDF bytes with DomPDF, then stamps "Page X of Y" in the footer of every page.
 * Images must be data URIs: remote fetching is off, so a template can never reach the network. Only the glyphs
 * used are embedded (font subsetting), which keeps a one-page document small.
 */
final class PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(DocumentLayout $layout, array $data, bool $landscape = false): string
    {
        $pdf = Pdf::loadView($layout->view(), $data)
            ->setPaper('a4', $landscape ? 'landscape' : 'portrait')
            ->setOption([
                'isRemoteEnabled' => false,
                'isFontSubsettingEnabled' => true,
                'defaultFont' => 'DejaVu Sans',
                'dpi' => 120,
            ]);

        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans');
        [$r, $g, $b] = sscanf(Config::string('documents.colors.muted'), '#%02x%02x%02x') ?? [100, 116, 139];
        $canvas->page_text($canvas->get_width() - 112, $canvas->get_height() - 30, (string) __('documents.letterhead.page'), $font, 7.5, [$r / 255, $g / 255, $b / 255]);

        return (string) $dompdf->output();
    }
}
