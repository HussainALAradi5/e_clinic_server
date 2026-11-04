<?php

declare(strict_types=1);

namespace App\Utilities\Transfer\Xlsx;

use RuntimeException;

/**
 * One worksheet of an XlsxWriter. Strings are written inline (no shared-strings table), numbers as numbers.
 *
 * A branded sheet (exports, reports) starts with banner(): a title band and a subtitle above the header row; its
 * header row then gets filters, the rows below are striped with thin borders, and the view freezes under the header.
 * Without a banner the header stays on row 1 (import templates, which are read back).
 */
final class XlsxSheet
{
    /** @var resource */
    private $handle;

    private readonly string $file;

    private int $rowNumber = 0;

    private int $headerRow = 0;

    private int $columns = 0;

    private bool $branded = false;

    /** @var list<string> */
    private array $merges = [];

    /**
     * @param  list<float|int>  $widths
     */
    public function __construct(
        public readonly string $name,
        private readonly array $widths,
        private readonly bool $freezeHeader,
    ) {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        $handle = fopen($this->file, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Cannot write the XLSX sheet.');
        }
        $this->handle = $handle;
    }

    /** Title band and subtitle across `$columns` columns, then a spacer row; call before header(). */
    public function banner(string $title, string $subtitle, int $columns): self
    {
        $this->branded = true;
        $this->columns = max(1, $columns);
        $last = self::columnLetter($this->columns - 1);

        $this->rawRow([$title], XlsxWriter::STYLE_TITLE, 30);
        $this->merges[] = "A{$this->rowNumber}:{$last}{$this->rowNumber}";
        $this->rawRow([$subtitle], XlsxWriter::STYLE_SUBTITLE, 18);
        $this->merges[] = "A{$this->rowNumber}:{$last}{$this->rowNumber}";
        $this->rawRow([], XlsxWriter::STYLE_DEFAULT, 8);

        return $this;
    }

    /**
     * @param  list<mixed>  $cells
     */
    public function header(array $cells): self
    {
        $this->columns = max($this->columns, count($cells));
        $this->rawRow($cells, XlsxWriter::STYLE_HEADER, $this->branded ? 22 : null);
        $this->headerRow = $this->rowNumber;

        return $this;
    }

    /**
     * @param  list<mixed>  $cells  strings, numbers, booleans or null
     */
    public function row(array $cells, int $style = XlsxWriter::STYLE_DEFAULT): self
    {
        if ($this->branded && $style === XlsxWriter::STYLE_DEFAULT && $this->headerRow > 0) {
            $style = ($this->rowNumber - $this->headerRow) % 2 === 0 ? XlsxWriter::STYLE_BODY : XlsxWriter::STYLE_STRIPE;
            // Striped rows keep their fill on empty cells too.
            $cells = array_pad(array_map(fn (mixed $v): mixed => $v ?? '', $cells), $this->columns, '');
        }

        return $this->rawRow($cells, $style);
    }

    /** Closes the sheet and returns the path of its complete XML part. */
    public function finish(): string
    {
        fclose($this->handle);
        $body = (string) file_get_contents($this->file);

        $cols = '';
        foreach ($this->widths as $i => $width) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
        }

        $frozen = $this->headerRow > 0 ? $this->headerRow : 1;
        $pane = $this->freezeHeader && $this->rowNumber > 0
            ? '<sheetViews><sheetView workbookViewId="0" showGridLines="'.($this->branded ? '0' : '1').'"><pane ySplit="'.$frozen.'" topLeftCell="A'.($frozen + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '';
        $filter = $this->branded && $this->headerRow > 0
            ? '<autoFilter ref="A'.$this->headerRow.':'.self::columnLetter($this->columns - 1).max($this->headerRow, $this->rowNumber).'"/>'
            : '';
        $merges = $this->merges === [] ? '' : '<mergeCells count="'.count($this->merges).'">'
            .implode('', array_map(fn (string $ref): string => '<mergeCell ref="'.$ref.'"/>', $this->merges)).'</mergeCells>';

        file_put_contents($this->file, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$pane.($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$body.'</sheetData>'.$filter.$merges.'</worksheet>');

        return $this->file;
    }

    /** 0 → A, 25 → Z, 26 → AA. */
    public static function columnLetter(int $index): string
    {
        $letters = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $letters = chr(65 + ($n - 1) % 26).$letters;
        }

        return $letters;
    }

    /** XML-escapes text and drops characters XML 1.0 does not allow. */
    public static function escape(string $text): string
    {
        $clean = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text);

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  list<mixed>  $cells
     */
    private function rawRow(array $cells, int $style, ?int $height = null): self
    {
        $this->rowNumber++;
        $xml = '<row r="'.$this->rowNumber.'"'.($height !== null ? ' ht="'.$height.'" customHeight="1"' : '').'>';

        foreach ($cells as $i => $value) {
            $s = $style !== XlsxWriter::STYLE_DEFAULT ? ' s="'.$style.'"' : '';
            if ($value === null || ($value === '' && $s === '')) {
                continue;
            }
            $ref = self::columnLetter($i).$this->rowNumber;
            $xml .= is_int($value) || is_float($value)
                ? '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>'
                : '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::escape(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value).'</t></is></c>';
        }

        fwrite($this->handle, $xml.'</row>');

        return $this;
    }
}
