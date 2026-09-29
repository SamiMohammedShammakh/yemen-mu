<?php

namespace Tests\Feature;

use App\Models\Export;
use App\Models\ExecutiveMembership;
use App\Models\PlanningYear;
use App\Models\Position;
use App\Services\Results;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/** حالات التنزيل 9–10 */
class DownloadTest extends TestCase
{
    private function create(array $in): Export
    {
        $this->post(route('exports.store'), $in)->assertRedirect(route('reports.index'));

        return Export::latest('id')->first();
    }

    /** 9. صاحب المنصب ينزّل خطته ونتائجها فقط، وأصحاب الصلاحية الشاملة ينزّلون أي خطة */
    public function test_owner_downloads_only_own_plan_and_global_users_download_any(): void
    {
        $eng = $this->plan(Position::ENGINEERING);
        $media = $this->plan(Position::MEDIA);

        $this->as(Position::ENGINEERING);
        $this->get(route('exports.preview', ['scope' => 'plan_full', 'format' => 'pdf', 'plan_id' => $eng->id]))
            ->assertOk()->assertSee('مسؤول الشؤون الهندسية')->assertSee('v1')->assertSee('معتمدة')->assertSee('تاريخ البيانات');
        foreach (['plan_full' => 'pdf', 'plan_quarter' => 'docx', 'results_annual' => 'xlsx', 'results_quarter' => 'csv', 'year_package' => 'zip'] as $scope => $fmt) {
            $e = $this->create(['scope' => $scope, 'format' => $fmt, 'plan_id' => $eng->id, 'quarter' => 2]);
            $this->assertSame($fmt, $e->format);
            $this->get(route('exports.download', $e))->assertOk()->assertDownload($e->file_name);
        }
        $this->post(route('exports.store'), ['scope' => 'plan_full', 'format' => 'pdf', 'plan_id' => $media->id])->assertForbidden();
        $this->post(route('exports.store'), ['scope' => 'org_summary', 'format' => 'pdf', 'planning_year_id' => $eng->planning_year_id, 'quarter' => 2])->assertForbidden();
        // نتيجة مؤشر من خطة أخرى عبر خطة يملكها: مرفوض (العنصر يجب أن يتبع الخطة)
        $this->post(route('exports.store'), ['scope' => 'indicator', 'format' => 'csv', 'plan_id' => $eng->id, 'subject_id' => $media->indicators()->first()->id, 'quarter' => 2])->assertNotFound();

        $mediaExport = app(\App\Services\Export\ExportService::class)->generate($this->user(Position::MEDIA), ['scope' => 'plan_full', 'format' => 'csv', 'plan_id' => $media->id]);
        $this->get(route('exports.download', $mediaExport))->assertForbidden();

        foreach ([Position::PRESIDENT, Position::PLANNING] as $code) {
            $this->as($code);
            $this->get(route('exports.download', $mediaExport))->assertOk();
            $this->create(['scope' => 'year_package', 'format' => 'zip', 'plan_id' => $media->id]);
            $org = $this->create(['scope' => 'org_summary', 'format' => 'xlsx', 'planning_year_id' => $media->planning_year_id, 'quarter' => 2]);
            $this->get(route('exports.download', $org))->assertOk();
        }
        // تسجيل من أنشأ ومن نزّل ومتى
        $this->assertDatabaseHas('export_downloads', ['export_id' => $mediaExport->id, 'user_id' => $this->user(Position::PRESIDENT)->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.create', 'user_id' => $this->user(Position::ENGINEERING)->id]);
    }

    public function test_permission_is_rechecked_at_download_time(): void
    {
        $vp = $this->user(Position::VICE_PRESIDENT);
        $m = ExecutiveMembership::create(['user_id' => $vp->id, 'granted_by' => $this->user(Position::PRESIDENT)->id, 'granted_at' => now(), 'grant_reason' => 'قرار']);
        $eng = $this->plan(Position::ENGINEERING);
        $e = $this->as(Position::VICE_PRESIDENT)->create(['scope' => 'plan_full', 'format' => 'csv', 'plan_id' => $eng->id]);
        $this->get(route('exports.download', $e))->assertOk();
        $m->update(['revoked_at' => now(), 'revoked_by' => $this->user(Position::PRESIDENT)->id]);
        $this->as(Position::VICE_PRESIDENT)->get(route('exports.download', $e))->assertForbidden();
    }

    /** 10. تطابق الملفات الأرقام المعروضة وتوضح السنة والربع والنسخة وحالة الاعتماد */
    public function test_files_match_system_numbers_and_carry_header(): void
    {
        $eng = $this->plan(Position::ENGINEERING);
        $training = $eng->indicators()->where('name', 'عدد البرامج التدريبية المنفذة')->first();
        $shown = (new Results())->plan($eng, 2);
        $this->as(Position::ENGINEERING);

        // CSV: أسطر الرأس + صف المؤشر بنفس الأرقام
        $csv = $this->create(['scope' => 'results_quarter', 'format' => 'csv', 'plan_id' => $eng->id, 'quarter' => 2]);
        $rows = array_map(fn ($l) => str_getcsv($l, ',', '"', ''), preg_split('/\R/u', trim(ltrim(Storage::disk('local')->get($csv->file_path), "\xEF\xBB\xBF"))));
        $head = array_column(array_slice($rows, 0, 9), 1, 0);
        $this->assertSame('مسؤول الشؤون الهندسية', $head['المنصب']);
        $this->assertSame('2026', $head['السنة']);
        $this->assertSame('الربع الثاني', $head['الربع']);
        $this->assertSame('v1', $head['رقم النسخة']);
        $this->assertSame('معتمدة', $head['حالة الخطة']);
        $row = collect($rows)->first(fn ($r) => ($r[2] ?? null) === $training->name);
        $this->assertEquals(5, $row[10]);   // المستهدف المرحلي
        $this->assertEquals(4, $row[11]);   // الفعلي المعتمد
        $this->assertEquals(80, $row[13]);  // الإنجاز مقابل المستهدف المرحلي
        $this->assertEquals(33.3, $row[17]); // الإنجاز مقابل المستهدف السنوي

        // XLSX: ورقة البيانات الرقمية تطابق الحساب، والملخص يحمل الرأس
        $x = $this->create(['scope' => 'results_quarter', 'format' => 'xlsx', 'plan_id' => $eng->id, 'quarter' => 2]);
        $book = IOFactory::load(Storage::disk('local')->path($x->file_path));
        $sum = $book->getSheet(0);
        $this->assertSame('v1', $sum->getCell('B6')->getValue());
        $this->assertSame('معتمدة', $sum->getCell('B7')->getValue());
        $this->assertTrue($sum->getRightToLeft());
        $data = $book->getSheetByName('بيانات رقمية')->toArray();
        $r = collect($data)->first(fn ($r) => ($r[2] ?? null) === $training->name);
        $this->assertEquals(80, $r[13]);
        $flat = array_merge(...$sum->toArray(null, true, false));
        $this->assertContains(round(round($shown['period_achievement'], 1) / 100, 4), array_map(fn ($v) => is_float($v) ? round($v, 4) : $v, $flat));

        // DOCX: الرأس يحمل المنصب والسنة والربع والنسخة والحالة
        $d = $this->create(['scope' => 'plan_full', 'format' => 'docx', 'plan_id' => $eng->id]);
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('local')->path($d->file_path));
        $hdr = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            if (str_starts_with($zip->getNameIndex($i), 'word/header')) $hdr .= $zip->getFromIndex($i);
        }
        foreach (['مسؤول الشؤون الهندسية', '2026', 'السنة كاملة', 'v1', 'حالة الخطة: معتمدة'] as $needle) {
            $this->assertStringContainsString($needle, $hdr);
        }

        // PDF: نص الصفحة يحمل الرأس ونسبة الإنجاز
        if (trim((string) shell_exec('which pdftotext'))) {
            $p = $this->create(['scope' => 'results_quarter', 'format' => 'pdf', 'plan_id' => $eng->id, 'quarter' => 2]);
            $txt = shell_exec('pdftotext -layout ' . escapeshellarg(Storage::disk('local')->path($p->file_path)) . ' -');
            // استخراج النص يعكس ترتيب العلامة في السياق العربي (80% ↔ %80)
            $this->assertMatchesRegularExpression('/91\.3%|%91\.3/u', $txt);
            $this->assertMatchesRegularExpression('/(?<![\d.])80%|%80(?![\d.])/u', $txt);
            $this->assertStringContainsString('2026', $txt);
        }

        // ZIP: الخطة + تقارير الأرباع الأربعة + فهرس
        $z = $this->create(['scope' => 'year_package', 'format' => 'zip', 'plan_id' => $eng->id]);
        $zip = new \ZipArchive();
        $zip->open(Storage::disk('local')->path($z->file_path));
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) $names[] = $zip->getNameIndex($i);
        $this->assertContains('00-index.csv', $names);
        foreach ([1, 2, 3, 4] as $q) {
            $this->assertContains("1{$q}-results-Q{$q}-engineering-2026.pdf", $names);
        }
        $this->assertContains('01-plan-engineering-2026.pdf', $names);
        $this->assertStringContainsString('رقم النسخة: v1', $zip->getFromName('00-index.csv'));
    }

    public function test_draft_plan_file_is_marked_as_draft_and_editing_file_changes_nothing(): void
    {
        $this->as(Position::MEDIA)->post(route('plans.store'), ['position_id' => Position::where('code', Position::MEDIA)->value('id'), 'planning_year_id' => PlanningYear::where('year', 2027)->value('id')]);
        $draft = $this->plan(Position::MEDIA, 2027);
        $this->get(route('exports.preview', ['scope' => 'plan_full', 'format' => 'csv', 'plan_id' => $draft->id]))->assertSee('مسودة غير معتمدة');
        $e = $this->create(['scope' => 'plan_full', 'format' => 'csv', 'plan_id' => $draft->id]);
        $content = Storage::disk('local')->get($e->file_path);
        $this->assertStringContainsString('"حالة الخطة",مسودة', $content);
        $this->assertStringContainsString('draft', $e->file_name);

        // تعديل الملف بعد إنشائه لا يغيّر الخطة
        $before = $draft->fresh()->toArray();
        Storage::disk('local')->put($e->file_path, str_replace('مسودة', 'معتمدة', $content));
        $this->assertEquals($before, $draft->fresh()->toArray());
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_closed_quarter_file_uses_snapshot_numbers(): void
    {
        $eng = $this->plan(Position::ENGINEERING);
        $this->as(Position::ENGINEERING);
        $e = $this->create(['scope' => 'results_quarter', 'format' => 'xlsx', 'plan_id' => $eng->id, 'quarter' => 1]);
        $flat = implode(' ', array_map('strval', array_merge(...IOFactory::load(Storage::disk('local')->path($e->file_path))->getSheet(0)->toArray())));
        $this->assertStringContainsString('لقطة إقفال الربع (مراجعة 1)', $flat);
        $this->get(route('plans.quarter', [$eng, 1]))->assertSee('النتائج من لقطة الإقفال');
    }
}
