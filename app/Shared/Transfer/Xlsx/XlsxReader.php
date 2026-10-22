<?php

declare(strict_types=1);

namespace App\Shared\Transfer\Xlsx;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Minimal XLSX reader on PHP's zip + XMLReader: yields each row of one worksheet as
 * [column index => text]. Shared strings, inline strings, numbers and booleans are supported;
 * formulas give their cached value. Dates stay Excel serial numbers (ImportColumn converts them).
 */
final class XlsxReader
{
    private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    /**
     * @return Generator<int, array<int, string>> Excel row number => cells
     */
    public function rows(string $path, ?string $preferredSheet = null): Generator
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Not a valid XLSX file.');
        }

        try {
            $sheetPath = $this->sheetPath($zip, $preferredSheet);
            $shared = $this->sharedStrings($zip);
            $xml = $zip->getFromName($sheetPath);
            if ($xml === false) {
                throw new RuntimeException('The workbook has no worksheet.');
            }
        } finally {
            $zip->close();
        }

        $reader = new XMLReader;
        $reader->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);

        $row = [];
        $rowNumber = 0;
        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'row') {
                $row = [];
                $rowNumber = (int) $reader->getAttribute('r') ?: $rowNumber + 1;
                if ($reader->isEmptyElement) {
                    continue;
                }
            } elseif ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'c') {
                $index = $this->columnIndex((string) $reader->getAttribute('r'), count($row));
                $row[$index] = $this->cellValue($reader, $shared);
            } elseif ($reader->nodeType === XMLReader::END_ELEMENT && $reader->localName === 'row') {
                yield $rowNumber => $row;
            }
        }
        $reader->close();
    }

    /**
     * @param  list<string>  $shared
     */
    private function cellValue(XMLReader $reader, array $shared): string
    {
        $type = (string) $reader->getAttribute('t');
        if ($reader->isEmptyElement) {
            return '';
        }

        $cell = $reader->readOuterXml();
        $node = simplexml_load_string($cell, options: LIBXML_NONET);
        if ($node === false) {
            return '';
        }
        $node->registerXPathNamespace('m', self::MAIN_NS);

        $value = match ($type) {
            'inlineStr' => implode('', array_map(strval(...), $node->xpath('.//m:t') ?: [])),
            's' => $shared[(int) $node->children(self::MAIN_NS)->v] ?? '',
            'b' => (string) $node->children(self::MAIN_NS)->v === '1' ? 'TRUE' : 'FALSE',
            default => (string) $node->children(self::MAIN_NS)->v,
        };

        return trim($value);
    }

    /**
     * @return list<string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }

        $doc = simplexml_load_string($xml, options: LIBXML_NONET);
        if ($doc === false) {
            return [];
        }
        $doc->registerXPathNamespace('m', self::MAIN_NS);

        return array_values(array_map(
            fn (\SimpleXMLElement $si): string => implode('', array_map(strval(...), $si->xpath('.//m:t') ?: [])),
            $doc->xpath('//m:si') ?: [],
        ));
    }

    /** The sheet called $preferred when present, otherwise the first sheet of the workbook. */
    private function sheetPath(ZipArchive $zip, ?string $preferred): string
    {
        $workbook = simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'), options: LIBXML_NONET);
        $rels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'), options: LIBXML_NONET);
        if ($workbook === false || $rels === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $targets = [];
        foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships') as $rel) {
            $attributes = $rel->attributes();
            $targets[(string) $attributes['Id']] = ltrim(str_replace('/xl/', '', (string) $attributes['Target']), '/');
        }

        $chosen = null;
        foreach ($workbook->children(self::MAIN_NS)->sheets->sheet ?? [] as $sheet) {
            $id = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $chosen ??= $id;
            if ($preferred !== null && strcasecmp((string) $sheet->attributes()['name'], $preferred) === 0) {
                $chosen = $id;
                break;
            }
        }

        return 'xl/'.($targets[$chosen] ?? 'worksheets/sheet1.xml');
    }

    /** "C7" → 2; cells without a reference follow the previous one. */
    private function columnIndex(string $reference, int $fallback): int
    {
        if (preg_match('/^([A-Z]+)/', $reference, $m) !== 1) {
            return $fallback;
        }

        $index = 0;
        foreach (str_split($m[1]) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }
}
