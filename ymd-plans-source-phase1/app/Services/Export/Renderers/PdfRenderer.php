<?php

namespace App\Services\Export\Renderers;

use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;

class PdfRenderer
{
    public static function mpdf(bool $landscape = true): Mpdf
    {
        $cfg = (new ConfigVariables())->getDefaults();
        $fonts = (new FontVariables())->getDefaults();
        $tmp = storage_path('app/private/mpdf');
        @mkdir($tmp, 0775, true);

        return new Mpdf([
            'tempDir' => $tmp,
            'format' => $landscape ? 'A4-L' : 'A4',
            'margin_top' => 30, 'margin_bottom' => 16, 'margin_left' => 12, 'margin_right' => 12, 'margin_header' => 8, 'margin_footer' => 6,
            'fontDir' => array_merge($cfg['fontDir'], [resource_path('fonts/pdf')]),
            'fontdata' => $fonts['fontdata'] + ['cairo' => ['R' => 'CairoPdf-Regular.ttf', 'B' => 'CairoPdf-Bold.ttf', 'useOTL' => 0xFF, 'useKashida' => 75]],
            'default_font' => 'cairo',
            'directionality' => 'rtl',
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);
    }

    public function render(array $blocks, array $h, string $path): void
    {
        $m = self::mpdf();
        $m->SetTitle($h['title'] . ' — ' . $h['position'] . ' ' . $h['year']);
        $m->SetAuthor($h['org']);
        $m->SetCreator('نظام الخطط السنوية والمتابعة');
        $m->SetHTMLHeader(view('exports.pdf-header', ['h' => $h])->render());
        $m->SetHTMLFooter('<table width="100%" style="font-size:8pt;color:#777;border:0"><tr><td style="border:0;text-align:right">' . e($h['org']) . ' — ' . e($h['position']) . ' — ' . e($h['year']) . ' — ' . e($h['version']) . ' — ' . e($h['status']) . '</td><td style="border:0;text-align:left">صفحة {PAGENO} من {nbpg}</td></tr></table>');
        if ($h['status'] === 'مسودة') {
            $m->SetWatermarkText('مسودة', 0.06);
            $m->showWatermarkText = true;
        }
        $m->WriteHTML(view('exports.pdf', ['blocks' => $blocks, 'h' => $h])->render());
        $m->Output($path, 'F');
    }
}
