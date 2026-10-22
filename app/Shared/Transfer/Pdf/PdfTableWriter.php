<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Pdf;

/**
 * Minimal PDF writer for list exports: a titled, paginated table on landscape A4 using the PDF base fonts
 * (Helvetica, no embedding) — no third-party library. Text outside Windows-1252 is replaced by "?".
 */
final class PdfTableWriter
{
    private const PAGE_WIDTH = 842.0;

    private const PAGE_HEIGHT = 595.0;

    private const MARGIN = 36.0;

    private const FONT_SIZE = 8.0;

    private const ROW_HEIGHT = 16.0;

    private const PADDING = 4.0;

    private const MIN_COLUMN = 40.0;

    /** Brand pine (header band) and the zebra tint, as PDF RGB triples. */
    private const PINE = '0.043 0.365 0.341';

    private const STRIPE = '0.953 0.969 0.965';

    /**
     * @param  list<string>  $headers
     * @param  iterable<list<string>>  $rows
     */
    public function write(string $path, string $title, string $subtitle, array $headers, iterable $rows): void
    {
        $rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
        $widths = $this->columnWidths($headers, $rows);

        $pages = [];
        $tableTop = self::PAGE_HEIGHT - self::MARGIN - 44;
        $perPage = (int) floor(($tableTop - self::MARGIN - 18) / self::ROW_HEIGHT) - 1;
        $chunks = $rows === [] ? [[]] : array_chunk($rows, max($perPage, 1));

        foreach ($chunks as $index => $chunk) {
            $pages[] = $this->page($title, $subtitle, $headers, $chunk, $widths, $tableTop, $index + 1, count($chunks), $index === 0 ? 0 : $index * $perPage);
        }

        file_put_contents($path, $this->document($pages));
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  list<float>  $widths
     */
    private function page(string $title, string $subtitle, array $headers, array $rows, array $widths, float $top, int $number, int $total, int $offset): string
    {
        $out = [];
        $x0 = self::MARGIN;

        // Title block.
        $out[] = $this->text($x0, self::PAGE_HEIGHT - self::MARGIN - 14, $title, 'F2', 15, '0.063 0.125 0.118');
        $out[] = $this->text($x0, self::PAGE_HEIGHT - self::MARGIN - 30, $subtitle, 'F1', 8.5, '0.337 0.408 0.396');

        // Header band.
        $tableWidth = array_sum($widths);
        $out[] = self::PINE.' rg '.$this->rect($x0, $top - self::ROW_HEIGHT, $tableWidth, self::ROW_HEIGHT).' f';
        $x = $x0;
        foreach ($headers as $i => $header) {
            $out[] = $this->text($x + self::PADDING, $top - 11, $this->fit($header, $widths[$i] - 2 * self::PADDING, true), 'F2', self::FONT_SIZE, '1 1 1');
            $x += $widths[$i];
        }

        // Body rows with zebra striping.
        $y = $top - self::ROW_HEIGHT;
        foreach ($rows as $r => $row) {
            if (($offset + $r) % 2 === 1) {
                $out[] = self::STRIPE.' rg '.$this->rect($x0, $y - self::ROW_HEIGHT, $tableWidth, self::ROW_HEIGHT).' f';
            }
            $x = $x0;
            foreach ($widths as $i => $width) {
                $out[] = $this->text($x + self::PADDING, $y - 11, $this->fit($row[$i] ?? '', $width - 2 * self::PADDING), 'F1', self::FONT_SIZE, '0.063 0.125 0.118');
                $x += $width;
            }
            $y -= self::ROW_HEIGHT;
        }

        // Bottom rule and page number.
        $out[] = '0.859 0.894 0.882 RG 0.5 w '.$x0.' '.$y.' m '.($x0 + $tableWidth).' '.$y.' l S';
        if ($rows === []) {
            $out[] = $this->text($x0 + self::PADDING, $y - 14, 'No records match the filters.', 'F1', self::FONT_SIZE, '0.337 0.408 0.396');
        }
        $label = "Page {$number} of {$total}";
        $out[] = $this->text(self::PAGE_WIDTH - self::MARGIN - $this->width($label, false, 7.5), self::MARGIN - 14, $label, 'F1', 7.5, '0.337 0.408 0.396');

        return implode("\n", $out);
    }

    /**
     * Widths proportional to the longest value per column (capped), scaled to the printable width.
     *
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @return list<float>
     */
    private function columnWidths(array $headers, array $rows): array
    {
        $available = self::PAGE_WIDTH - 2 * self::MARGIN;
        $natural = [];
        foreach ($headers as $i => $header) {
            $longest = $this->width($header, true);
            foreach (array_slice($rows, 0, 300) as $row) {
                $longest = max($longest, $this->width((string) ($row[$i] ?? '')));
            }
            $natural[] = min(max($longest + 2 * self::PADDING, self::MIN_COLUMN), $available * 0.4);
        }

        $scale = $available / max(array_sum($natural), 1);

        return array_map(fn (float $w): float => round($w * $scale, 2), $natural);
    }

    /** Text cut to the width with an ellipsis. */
    private function fit(string $text, float $max, bool $bold = false): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($this->width($text, $bold) <= $max) {
            return $text;
        }
        while ($text !== '' && $this->width($text.'...', $bold) > $max) {
            $text = mb_substr($text, 0, -1);
        }

        return $text.'...';
    }

    private function width(string $text, bool $bold = false, float $size = self::FONT_SIZE): float
    {
        $metrics = $bold ? HelveticaMetrics::BOLD : HelveticaMetrics::REGULAR;
        $units = 0;
        foreach (mb_str_split($text) as $char) {
            $units += $metrics[$char] ?? 556;
        }

        return $units * $size / 1000;
    }

    private function text(float $x, float $y, string $text, string $font, float $size, string $color): string
    {
        return sprintf('BT %s rg /%s %.1F Tf %.2F %.2F Td (%s) Tj ET', $color, $font, $size, $x, $y, $this->pdfString($text));
    }

    private function rect(float $x, float $y, float $w, float $h): string
    {
        return sprintf('%.2F %.2F %.2F %.2F re', $x, $y, $w, $h);
    }

    /** Windows-1252 bytes with PDF string escapes. */
    private function pdfString(string $text): string
    {
        $encoded = (string) @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return strtr($encoded, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => ' ', "\n" => ' ']);
    }

    /**
     * @param  list<string>  $pages  content streams
     */
    private function document(array $pages): string
    {
        $objects = [];
        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';

        $kids = [];
        $next = 5;
        foreach ($pages as $content) {
            $pageId = $next++;
            $contentId = $next++;
            $kids[] = "{$pageId} 0 R";
            $objects[$pageId] = sprintf('<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.0F %.0F] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents %d 0 R >>', self::PAGE_WIDTH, self::PAGE_HEIGHT, $contentId);
            $objects[$contentId] = '<< /Length '.strlen($content)." >>\nstream\n{$content}\nendstream";
        }
        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.count($kids).' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
