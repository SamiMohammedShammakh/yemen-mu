<?php

namespace App\Services\Export\Renderers;

/** CSV بترميز UTF-8 مع BOM ليفتح بالعربية في Excel. أسطر الرأس الأولى تحمل بيانات الملف. */
class CsvRenderer
{
    public function render(array $data, array $h, string $path): void
    {
        $f = fopen($path, 'w');
        fwrite($f, "\xEF\xBB\xBF");
        foreach ([['الجهة', $h['org']], ['التقرير', $h['title']], ['المنصب', $h['position']], ['السنة', $h['year']], ['الربع', $h['quarter']],
            ['رقم النسخة', $h['version']], ['حالة الخطة', $h['status']], ['تاريخ البيانات', $h['data_as_of']], ['تاريخ إصدار الملف', $h['issued_at']]] as $row) {
            fputcsv($f, $row, ',', '"', '');
        }
        fputcsv($f, [], ',', '"', '');
        fputcsv($f, $data['columns'], ',', '"', '');
        foreach ($data['rows'] as $row) {
            fputcsv($f, array_map(fn ($v) => $v === null ? '' : $v, array_values($row)), ',', '"', '');
        }
        if (! empty($data['second'])) {
            fputcsv($f, [], ',', '"', '');
            fputcsv($f, [$data['second']['title']], ',', '"', '');
            fputcsv($f, $data['second']['columns'], ',', '"', '');
            foreach ($data['second']['rows'] as $row) {
                fputcsv($f, array_map(fn ($v) => $v === null ? '' : $v, array_values($row)), ',', '"', '');
            }
        }
        fclose($f);
    }
}
