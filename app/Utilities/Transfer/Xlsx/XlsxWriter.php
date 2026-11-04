<?php

declare(strict_types=1);

namespace App\Utilities\Transfer\Xlsx;

use RuntimeException;
use ZipArchive;

/**
 * Minimal streaming XLSX writer (Office Open XML) on PHP's own zip extension — no third-party library.
 * Rows are written straight to a temporary file per sheet, so large exports stay light on memory.
 *
 *   $xlsx = new XlsxWriter;
 *   $xlsx->addSheet('Data', [18, 30])->header(['Code', 'Name'])->row(['C-1', 'Acme']);
 *   $xlsx->save('/tmp/out.xlsx');
 */
final class XlsxWriter
{
    /** Cell styles in styles.xml. */
    public const STYLE_DEFAULT = 0;

    public const STYLE_HEADER = 1;

    public const STYLE_BOLD = 2;

    public const STYLE_WRAP = 3;

    /** Branded sheets (XlsxSheet::banner): title band, subtitle, bordered body rows and their striped twins. */
    public const STYLE_TITLE = 4;

    public const STYLE_SUBTITLE = 5;

    public const STYLE_BODY = 6;

    public const STYLE_STRIPE = 7;

    /** @var list<XlsxSheet> */
    private array $sheets = [];

    /**
     * @param  list<float|int>  $widths  column widths in characters
     */
    public function addSheet(string $name, array $widths = [], bool $freezeHeader = true): XlsxSheet
    {
        $sheet = new XlsxSheet($this->safeName($name), $widths, $freezeHeader);
        $this->sheets[] = $sheet;

        return $sheet;
    }

    public function save(string $path): void
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Cannot create the XLSX file.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());

        $finished = [];
        foreach ($this->sheets as $i => $sheet) {
            $finished[] = $file = $sheet->finish();
            $zip->addFile($file, 'xl/worksheets/sheet'.($i + 1).'.xml');
        }

        $zip->close();
        array_map(unlink(...), $finished);
    }

    private function contentTypes(): string
    {
        $sheets = '';
        foreach (array_keys($this->sheets) as $i) {
            $sheets .= '<Override PartName="/xl/worksheets/sheet'.($i + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$sheets.'</Types>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $i => $sheet) {
            $sheets .= '<sheet name="'.XlsxSheet::escape($sheet->name).'" sheetId="'.($i + 1).'" r:id="rId'.($i + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach (array_keys($this->sheets) as $i) {
            $rels .= '<Relationship Id="rId'.($i + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($i + 1).'.xml"/>';
        }
        $styles = count($this->sheets) + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels
            .'<Relationship Id="rId'.$styles.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /**
     * 0 default, 1 header (bold white on pine), 2 bold, 3 wrapped text, 4 title (large pine on mint band),
     * 5 subtitle (grey italic), 6 body row (thin grey bottom border), 7 striped body row.
     */
    private function styles(): string
    {
        $center = '<alignment vertical="center"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="5"><font><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="16"/><color rgb="FF073C39"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF64748B"/><name val="Calibri"/></font></fonts>'
            .'<fills count="5"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF0B5D57"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE6F4F1"/><bgColor indexed="64"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFF8FAFC"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left/><right/><top/><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="8"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'.$center.'</xf>'
            .'<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment wrapText="1" vertical="top"/></xf>'
            .'<xf numFmtId="0" fontId="3" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyAlignment="1">'.$center.'</xf>'
            .'<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1">'.$center.'</xf>'
            .'<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'.$center.'</xf>'
            .'<xf numFmtId="0" fontId="0" fillId="4" borderId="1" xfId="0" applyFill="1" applyBorder="1" applyAlignment="1">'.$center.'</xf></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    /** Excel sheet names: ≤ 31 chars, none of : \ / ? * [ ]. */
    private function safeName(string $name): string
    {
        return mb_substr(trim((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $name)) ?: 'Sheet', 0, 31);
    }
}
