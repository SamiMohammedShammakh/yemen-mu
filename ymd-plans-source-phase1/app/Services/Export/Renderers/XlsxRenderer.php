<?php

namespace App\Services\Export\Renderers;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/** جداول النتائج: ورقة ملخص + ورقة لكل جدول + أوراق بيانات رقمية للمؤشرات والتحديثات */
class XlsxRenderer
{
    private array $names = [];

    public function render(array $blocks, array $h, string $path, ?array $data = null): void
    {
        $x = new Spreadsheet();
        $x->getProperties()->setTitle($h['title'])->setCreator($h['org'])->setSubject($h['position'] . ' ' . $h['year'] . ' ' . $h['version'])->setDescription($h['status']);
        $sum = $x->getActiveSheet();
        $sum->setTitle($this->name('ملخص'));
        $r = $this->header($sum, $h);
        $lastHeading = 'جدول';
        foreach ($blocks as $b) {
            switch ($b[0]) {
                case 'h1': break;
                case 'h2':
                case 'h3':
                    $lastHeading = $b[1];
                    $sum->setCellValue("A$r", $b[1]);
                    $sum->getStyle("A$r")->getFont()->setBold(true)->getColor()->setRGB('1E9C80');
                    $r++;
                    break;
                case 'p': case 'note': case 'warn':
                    $sum->setCellValue("A$r", (string) $b[1]);
                    $r++;
                    break;
                case 'list':
                    foreach ($b[1] as $l) {
                        $sum->setCellValue("A$r", '• ' . $l);
                        $r++;
                    }
                    break;
                case 'kv':
                    foreach ($b[1] as [$k, $v]) {
                        $sum->setCellValue("A$r", (string) $k);
                        $this->put($sum, "B$r", $v);
                        $sum->getStyle("A$r")->getFont()->setBold(true);
                        $r++;
                    }
                    $r++;
                    break;
                case 'table':
                    $ws = $x->createSheet();
                    $ws->setTitle($this->name($lastHeading));
                    $this->table($ws, $h, $b[1], $b[2]);
                    $sum->setCellValue("A$r", '← ورقة: ' . $ws->getTitle());
                    $r++;
                    break;
            }
        }
        $sum->getColumnDimension('A')->setWidth(42);
        $sum->getColumnDimension('B')->setWidth(90);
        if ($data) {
            $ws = $x->createSheet();
            $ws->setTitle($this->name('بيانات رقمية'));
            $this->table($ws, $h, $data['columns'], $data['rows'], true);
            if (! empty($data['second'])) {
                $ws2 = $x->createSheet();
                $ws2->setTitle($this->name($data['second']['title']));
                $this->table($ws2, $h, $data['second']['columns'], $data['second']['rows'], true);
            }
        }
        foreach ($x->getAllSheets() as $ws) {
            $ws->setRightToLeft(true);
        }
        $x->setActiveSheetIndex(0);
        (new Xlsx($x))->save($path);
    }

    private function name(string $n): string
    {
        $n = mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/u', ' ', $n), 0, 28);
        $base = $n;
        $i = 2;
        while (in_array($n, $this->names, true)) {
            $n = mb_substr($base, 0, 25) . ' ' . $i++;
        }
        $this->names[] = $n;

        return $n;
    }

    private function header(Worksheet $ws, array $h): int
    {
        $rows = [[$h['org']], [$h['title']],
            ['المنصب', $h['position']], ['السنة', $h['year']], ['الربع', $h['quarter']], ['رقم النسخة', $h['version']],
            ['حالة الخطة', $h['status']], ['تاريخ البيانات', $h['data_as_of']], ['تاريخ إصدار الملف', $h['issued_at']]];
        foreach ($rows as $i => $row) {
            $ws->fromArray($row, null, 'A' . ($i + 1));
        }
        $ws->getStyle('A1:A2')->getFont()->setBold(true)->setSize(13);
        $ws->getStyle('A3:A9')->getFont()->setBold(true);
        if ($h['status'] === 'مسودة') {
            $ws->getStyle('B7')->getFont()->setBold(true)->getColor()->setRGB('B45309');
        }

        return count($rows) + 2;
    }

    private function put(Worksheet $ws, string $cell, $v): void
    {
        if (is_string($v)) {
            $clean = str_replace(',', '', $v);
            if (preg_match('/^-?\d+(\.\d+)?%$/', $clean)) {
                $ws->setCellValue($cell, (float) rtrim($clean, '%') / 100);
                $ws->getStyle($cell)->getNumberFormat()->setFormatCode('0.0%');

                return;
            }
            if (preg_match('/^-?\d+(\.\d+)?$/', $clean) && strlen($clean) < 15) {
                $ws->setCellValue($cell, (float) $clean);

                return;
            }
        }
        $ws->setCellValue($cell, $v);
    }

    private function table(Worksheet $ws, array $h, array $cols, array $rows, bool $raw = false): void
    {
        $r = $this->header($ws, $h);
        foreach ($cols as $i => $c) {
            $ws->setCellValue(Coordinate::stringFromColumnIndex($i + 1) . $r, $c);
        }
        $last = Coordinate::stringFromColumnIndex(max(1, count($cols)));
        $ws->getStyle("A$r:$last$r")->applyFromArray(['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2BBB9A']]]);
        $ws->freezePane('A' . ($r + 1));
        foreach ($rows as $row) {
            $r++;
            foreach (array_values($row) as $i => $v) {
                $cell = Coordinate::stringFromColumnIndex($i + 1) . $r;
                $raw ? $ws->setCellValue($cell, $v) : $this->put($ws, $cell, $v);
            }
        }
        for ($i = 1; $i <= count($cols); $i++) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($i))->setWidth(20);
        }
        $ws->getColumnDimension('A')->setWidth(34);
        if (count($cols) > 2) {
            $ws->getColumnDimension('B')->setWidth(28);
        }
    }
}
