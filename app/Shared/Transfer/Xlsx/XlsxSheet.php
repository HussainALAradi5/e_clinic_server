<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Xlsx;

use RuntimeException;

/**
 * One worksheet of an XlsxWriter. Strings are written inline (no shared-strings table), numbers as numbers.
 */
final class XlsxSheet
{
    /** @var resource */
    private $handle;

    private readonly string $file;

    private int $rowNumber = 0;

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

    /**
     * @param  list<mixed>  $cells
     */
    public function header(array $cells): self
    {
        return $this->row($cells, XlsxWriter::STYLE_HEADER);
    }

    /**
     * @param  list<mixed>  $cells  strings, numbers, booleans or null
     */
    public function row(array $cells, int $style = XlsxWriter::STYLE_DEFAULT): self
    {
        $this->rowNumber++;
        $xml = '<row r="'.$this->rowNumber.'">';

        foreach ($cells as $i => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $ref = self::columnLetter($i).$this->rowNumber;
            $s = $style !== XlsxWriter::STYLE_DEFAULT ? ' s="'.$style.'"' : '';
            $xml .= is_int($value) || is_float($value)
                ? '<c r="'.$ref.'"'.$s.'><v>'.$value.'</v></c>'
                : '<c r="'.$ref.'"'.$s.' t="inlineStr"><is><t xml:space="preserve">'.self::escape(is_bool($value) ? ($value ? 'Yes' : 'No') : (string) $value).'</t></is></c>';
        }

        fwrite($this->handle, $xml.'</row>');

        return $this;
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

        $pane = $this->freezeHeader && $this->rowNumber > 0
            ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            : '';

        file_put_contents($this->file, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .$pane.($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$body.'</sheetData></worksheet>');

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
}
