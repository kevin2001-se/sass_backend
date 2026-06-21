<?php

namespace App\Services\Support;

use RuntimeException;
use ZipArchive;

class SimpleXlsxWriter
{
    public function make(string $title, array $headers, array $rows, array $summary = []): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('El servidor no tiene ZipArchive habilitado para generar XLSX.');
        }

        $path = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($path === false) {
            throw new RuntimeException('No se pudo crear el archivo temporal XLSX.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo XLSX para escritura.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypes());
        $zip->addFromString('_rels/.rels', $this->rels());
        $zip->addFromString('docProps/app.xml', $this->appProps());
        $zip->addFromString('docProps/core.xml', $this->coreProps());
        $zip->addFromString('xl/workbook.xml', $this->workbook());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheet($title, $headers, $rows, $summary));
        $zip->close();

        return $path;
    }

    protected function worksheet(string $title, array $headers, array $rows, array $summary): string
    {
        $sheetRows = [];
        $rowIndex = 1;
        $columnCount = max(count($headers), 2);

        $sheetRows[] = $this->row($rowIndex++, [$title], 1);
        $sheetRows[] = $this->row($rowIndex++, ['Generado', now()->format('Y-m-d H:i:s')]);

        if ($summary) {
            $rowIndex++;
            foreach ($summary as $key => $value) {
                $sheetRows[] = $this->row($rowIndex++, [$this->label($key), $this->stringValue($value)]);
            }
        }

        $rowIndex++;
        $sheetRows[] = $this->row($rowIndex++, array_map(fn ($header) => $this->label($header), $headers), 2);

        foreach ($rows as $row) {
            $values = array_is_list($row)
                ? $row
                : collect($headers)->map(fn ($header) => $row[$header] ?? '')->all();

            $sheetRows[] = $this->row($rowIndex++, array_map(fn ($value) => $this->stringValue($value), $values));
        }

        if (! $rows) {
            $sheetRows[] = $this->row($rowIndex++, ['Sin datos']);
        }

        $dimension = 'A1:'.$this->columnName($columnCount).max(1, $rowIndex - 1);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<dimension ref="'.$dimension.'"/>'
            .'<sheetViews><sheetView workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="18"/>'
            .'<sheetData>'.implode('', $sheetRows).'</sheetData>'
            .'</worksheet>';
    }

    protected function row(int $index, array $values, int $style = 0): string
    {
        $cells = [];
        foreach (array_values($values) as $column => $value) {
            $cells[] = $this->cell($this->columnName($column + 1).$index, $value, $style);
        }

        return '<row r="'.$index.'">'.implode('', $cells).'</row>';
    }

    protected function cell(string $ref, mixed $value, int $style = 0): string
    {
        $styleAttr = $style > 0 ? ' s="'.$style.'"' : '';
        return '<c r="'.$ref.'" t="inlineStr"'.$styleAttr.'><is><t>'.$this->escape($this->stringValue($value)).'</t></is></c>';
    }

    protected function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }

    protected function label(string|int $value): string
    {
        return str_replace('_', ' ', mb_strtoupper((string) $value));
    }

    protected function stringValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'SI' : 'NO';
        }

        if (is_array($value)) {
            return collect($value)->filter(fn ($item) => ! is_array($item))->implode(' | ');
        }

        return (string) ($value ?? '');
    }

    protected function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }

    protected function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    protected function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    protected function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Datos" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    protected function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    protected function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FFEFF4FF"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0" xfId="0"/></cellXfs>'
            .'</styleSheet>';
    }

    protected function coreProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>Botica SaaS</dc:creator><cp:lastModifiedBy>Botica SaaS</cp:lastModifiedBy>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.now()->toAtomString().'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.now()->toAtomString().'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    protected function appProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>Botica SaaS</Application>'
            .'</Properties>';
    }
}