<?php

namespace App\Services\Export\Renderers;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/** نسخة قابلة للتحرير من الخطة/التقرير — تعديلها لا يغيّر النظام */
class DocxRenderer
{
    public function render(array $blocks, array $h, string $path): void
    {
        $w = new PhpWord();
        $w->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('ar-SA'));
        $w->setDefaultFontName('Cairo');
        $w->setDefaultFontSize(10);
        $w->getDocInfo()->setTitle($h['title'])->setCreator($h['org'])->setSubject($h['position'] . ' ' . $h['year'] . ' ' . $h['version']);
        $rtl = ['rtl' => true, 'name' => 'Cairo'];
        $p = ['bidi' => true, 'alignment' => Jc::START];
        $w->addTitleStyle(1, $rtl + ['size' => 16, 'bold' => true, 'color' => '1E9C80'], $p);
        $w->addTitleStyle(2, $rtl + ['size' => 13, 'bold' => true, 'color' => '333333'], $p + ['spaceBefore' => 240]);
        $w->addTitleStyle(3, $rtl + ['size' => 11, 'bold' => true, 'color' => '1E9C80'], $p + ['spaceBefore' => 160]);

        $s = $w->addSection(['orientation' => 'landscape', 'marginLeft' => 700, 'marginRight' => 700, 'marginTop' => 900, 'marginBottom' => 700]);
        $header = $s->addHeader();
        $ht = $header->addTable(['bidiVisual' => true, 'borderBottomSize' => 6, 'borderBottomColor' => '2BBB9A', 'width' => 100 * 50, 'unit' => 'pct']);
        $ht->addRow();
        $logo = resource_path('img/logo-full.png');
        $c1 = $ht->addCell(9000);
        $c1->addText($h['org'], $rtl + ['bold' => true, 'size' => 10], $p);
        $c1->addText($h['position'] . ' | ' . $h['year'] . ' | ' . $h['quarter'] . ' | النسخة: ' . $h['version'] . ' | حالة الخطة: ' . $h['status'] . ' | الإصدار: ' . $h['issued_at'], $rtl + ['size' => 8, 'color' => '555555'], $p);
        if (is_file($logo)) {
            $ht->addCell(3000)->addImage($logo, ['height' => 32, 'alignment' => Jc::END]);
        }
        $s->addFooter()->addPreserveText('صفحة {PAGE} من {NUMPAGES} — ' . $h['position'] . ' ' . $h['year'] . ' — ' . $h['status'], $rtl + ['size' => 8, 'color' => '777777'], ['alignment' => Jc::CENTER]);

        $cellText = $rtl + ['size' => 9];
        foreach ($blocks as $b) {
            switch ($b[0]) {
                case 'h1': $s->addTitle($b[1], 1); break;
                case 'h2': $s->addTitle($b[1], 2); break;
                case 'h3': $s->addTitle($b[1], 3); break;
                case 'p': $s->addText((string) $b[1], $rtl, $p); break;
                case 'note': $s->addText((string) $b[1], $rtl + ['size' => 9, 'color' => '666666', 'italic' => true], $p); break;
                case 'warn': $s->addText((string) $b[1], $rtl + ['bold' => true, 'color' => 'B45309'], $p); break;
                case 'list':
                    foreach ($b[1] as $line) {
                        $s->addListItem((string) $line, 0, $rtl + ['size' => 9], null, $p);
                    }
                    break;
                case 'kv':
                    $t = $s->addTable(['bidiVisual' => true, 'borderSize' => 4, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);
                    foreach ($b[1] as [$k, $v]) {
                        $t->addRow();
                        $t->addCell(3500, ['bgColor' => 'E9F7F3'])->addText((string) $k, $cellText + ['bold' => true], $p);
                        $t->addCell(11000)->addText((string) ($v ?? '—'), $cellText, $p);
                    }
                    $s->addTextBreak(1);
                    break;
                case 'table':
                    $t = $s->addTable(['bidiVisual' => true, 'borderSize' => 4, 'borderColor' => 'BBBBBB', 'cellMargin' => 50, 'width' => 100 * 50, 'unit' => 'pct']);
                    $t->addRow(null, ['tblHeader' => true]);
                    foreach ($b[1] as $col) {
                        $t->addCell(null, ['bgColor' => '2BBB9A'])->addText((string) $col, $cellText + ['bold' => true, 'color' => 'FFFFFF'], $p);
                    }
                    foreach ($b[2] as $row) {
                        $t->addRow();
                        foreach ($row as $cell) {
                            $t->addCell()->addText(htmlspecialchars((string) ($cell ?? '—'), ENT_XML1), $cellText, $p);
                        }
                    }
                    $s->addTextBreak(1);
                    break;
            }
        }
        $w->save($path, 'Word2007');
    }
}
