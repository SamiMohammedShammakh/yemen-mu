<?php

namespace App\Http\Controllers;

use App\Models\Export;
use App\Models\ExportDownload;
use App\Models\Plan;
use App\Services\Export\ExportService;
use App\Support\Access;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * التقارير والتنزيلات: معاينة ← إنشاء ← تنزيل. الصلاحية تُفحص في كل خطوة على الخادم.
 */
class ExportController extends Controller
{
    public function __construct(private ExportService $svc = new ExportService()) {}

    public function index(Request $r)
    {
        $u = $r->user();
        $year = $this->ctx()->year;
        $plans = $year ? Access::visiblePlans($u)->where('planning_year_id', $year->id)->with(['position', 'objectives.indicators', 'projects'])->get()->sortBy('position.sort')->values() : collect();
        $exports = Export::with(['plan.position', 'user', 'year'])
            ->when(! Access::hasGlobalView($u), fn ($q) => $q->where('user_id', $u->id)->whereIn('plan_id', Access::visiblePlans($u)->select('id')))
            ->latest()->paginate(25);
        $global = Access::hasGlobalView($u);

        return view('reports.index', compact('plans', 'exports', 'global'));
    }

    public function preview(Request $r)
    {
        $in = $r->only(['scope', 'format', 'plan_id', 'planning_year_id', 'quarter', 'subject_id']);
        $in['quarter'] ??= $this->ctx()->quarter;
        $in['planning_year_id'] ??= $this->ctx()->year?->id;
        $p = $this->svc->preview($r->user(), $in);

        return view('exports.preview', ['p' => $p, 'in' => $in]);
    }

    public function store(Request $r)
    {
        $in = $r->only(['scope', 'format', 'plan_id', 'planning_year_id', 'quarter', 'subject_id']);
        $export = $this->svc->generate($r->user(), $in);

        return redirect()->route('reports.index')->with('ok', 'أُنشئ الملف ' . $export->file_name . '.')->with('download', route('exports.download', $export));
    }

    public function download(Request $r, Export $export)
    {
        $u = $r->user();
        // صاحب المنصب لا ينزّل إلا ملفات خطته، والملف الشامل لأصحاب الرؤية الشاملة فقط
        $this->svc->authorizeDownload($u, $export);
        abort_unless(Storage::disk('local')->exists($export->file_path), 404, 'الملف غير موجود؛ أنشئه من جديد.');
        ExportDownload::create(['export_id' => $export->id, 'user_id' => $u->id, 'ip' => $r->ip(), 'created_at' => now()]);
        $export->increment('download_count');
        Audit::log('export.download', $export, null, ['file' => $export->file_name], $export->plan_id);

        return Storage::disk('local')->download($export->file_path, $export->file_name);
    }
}
