<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Minimal XLSX writer (Office Open XML) on ZipArchive, so reports export as a
 * real Excel workbook with a header block, bold column titles, numbers stored
 * as numbers and an Arabic sheet shown right-to-left. No vendor library.
 */
class Ha_xlsx {

    /**
     * @param array $meta   title, lines (array of strings shown above the table), rtl (bool), sheet
     * @param array $header column titles
     * @param array $rows   list of row arrays
     * @return string xlsx binary
     */
    public function build(array $meta, array $header, array $rows) {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('The zip extension is required for Excel export.');
        }
        $tmp = tempnam(sys_get_temp_dir(), 'hkpx');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $sheet_name = $this->x(mb_substr(isset($meta['sheet']) ? $meta['sheet'] : 'Report', 0, 31));
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . $sheet_name . '" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/styles.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="3"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="14"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFE8EFEF"/></patternFill></fill></fills><borders count="1"><border/></borders><cellStyleXfs count="1"><xf/></cellStyleXfs><cellXfs count="4"><xf/><xf fontId="1" fillId="2" applyFont="1" applyFill="1"/><xf fontId="2" applyFont="1"/><xf numFmtId="4" applyNumberFormat="1"/></cellXfs></styleSheet>');

        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"' . (!empty($meta['rtl']) ? ' rightToLeft="1"' : '') . '/></sheetViews><sheetData>';
        $r = 1;
        if (!empty($meta['title'])) {
            $xml .= '<row r="' . $r . '">' . $this->cell(0, $r, $meta['title'], 2) . '</row>';
            $r++;
        }
        foreach (isset($meta['lines']) ? $meta['lines'] : array() as $line) {
            $xml .= '<row r="' . $r . '">' . $this->cell(0, $r, $line, 0) . '</row>';
            $r++;
        }
        if ($r > 1) {
            $r++;
        }
        $xml .= '<row r="' . $r . '">';
        foreach (array_values($header) as $i => $h) {
            $xml .= $this->cell($i, $r, $h, 1);
        }
        $xml .= '</row>';
        foreach ($rows as $row) {
            $r++;
            $xml .= '<row r="' . $r . '">';
            foreach (array_values($row) as $i => $v) {
                $xml .= $this->cell($i, $r, $v, 0);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData></worksheet>';
        $zip->addFromString('xl/worksheets/sheet1.xml', $xml);
        $zip->close();
        $bin = file_get_contents($tmp);
        @unlink($tmp);
        return $bin;
    }

    protected function cell($col, $row, $v, $style) {
        $ref = $this->col($col) . $row;
        $s = $style ? ' s="' . $style . '"' : '';
        if (is_int($v) || is_float($v) || (is_string($v) && preg_match('/^-?\d+(\.\d+)?$/', $v) && strlen($v) < 15 && !preg_match('/^0\d/', $v))) {
            return '<c r="' . $ref . '"' . $s . '><v>' . $v . '</v></c>';
        }
        return '<c r="' . $ref . '" t="inlineStr"' . $s . '><is><t xml:space="preserve">' . $this->x((string) $v) . '</t></is></c>';
    }

    protected function col($i) {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = (int) (($i - $m) / 26);
        }
        return $s;
    }

    protected function x($s) {
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', (string) $s);
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
