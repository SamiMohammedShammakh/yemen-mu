@extends('layouts.app')
@section('title', 'التقارير والتنزيلات')
@php use App\Support\Fmt; use App\Support\Workspace; use App\Models\Export; use App\Services\Export\ExportService; @endphp
@section('content')
<div class="ws-title"><h1>التقارير والتنزيلات</h1><span class="pill">{{ $ctx->year?->year }} · {{ Workspace::quarterName($ctx->quarter) }}</span></div>

<div class="grid g-2-1">
    <div class="card">
        <h2>إنشاء ملف</h2>
        @if ($plans->isEmpty())
            <div class="empty">لا توجد خطط متاحة لك في هذه السنة.</div>
        @else
        <form method="get" action="{{ route('exports.preview') }}" id="rep">
            <div class="form-grid">
                <div class="field"><label class="f">الخطة</label>
                    <select name="plan_id" id="r-plan">@foreach ($plans as $p)<option value="{{ $p->id }}">{{ $p->position->name }} — {{ $p->statusLabel() }} ({{ $p->versionLabel() }})</option>@endforeach</select></div>
                <div class="field"><label class="f">المحتوى</label>
                    <select name="scope" id="r-scope">
                        @foreach (Export::SCOPES as $k => $l)
                            @continue($k === 'org_summary')
                            <option value="{{ $k }}" data-formats="{{ implode(',', ExportService::SCOPE_FORMATS[$k]) }}">{{ $l }}</option>
                        @endforeach
                    </select></div>
                <div class="field" id="r-q-wrap"><label class="f">الربع</label>
                    <select name="quarter">@for ($k = 1; $k <= 4; $k++)<option value="{{ $k }}" @selected($k === $ctx->quarter)>{{ Workspace::quarterName($k) }}</option>@endfor</select>
                    <div class="hint">للنتائج السنوية: حتى نهاية هذا الربع.</div></div>
                <div class="field" id="r-sub-wrap"><label class="f" id="r-sub-label">العنصر</label>
                    <select name="subject_id" id="r-sub"></select></div>
                <div class="field"><label class="f">الصيغة</label><select name="format" id="r-format"></select></div>
            </div>
            <button class="btn">معاينة قبل الإنشاء</button>
        </form>
        @endif
    </div>
    <div class="card">
        <h2>الصيغ المتاحة</h2>
        <table class="kv small">
            <tr><th>PDF</th><td>للطباعة والمشاركة، مع رأس يوضح المنصب والسنة والربع والنسخة وحالة الاعتماد.</td></tr>
            <tr><th>DOCX</th><td>نسخة قابلة للتحرير من الخطة.</td></tr>
            <tr><th>XLSX</th><td>جداول النتائج والأهداف والمؤشرات والأرباع.</td></tr>
            <tr><th>CSV</th><td>بيانات المؤشرات والتحديثات.</td></tr>
            <tr><th>ZIP</th><td>حزمة السنة: الخطة وتقارير أرباعها وفهرس الملفات.</td></tr>
        </table>
        @if ($global && $ctx->year)
            <hr><h3>تقرير شامل لأداء الجمعية</h3>
            @include('partials.download', ['scope' => 'org_summary', 'year' => $ctx->year->id, 'quarter' => $ctx->quarter, 'label' => 'مقارنة المناصب'])
        @endif
    </div>
</div>

<div class="card">
    <h2>{{ $global ? 'سجل الملفات المنشأة' : 'ملفاتي' }}</h2>
    @if ($exports->isEmpty())
        <p class="muted small">لا توجد ملفات بعد.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الملف</th><th>المنصب</th><th>المحتوى</th><th>السنة/الربع</th><th>النسخة</th><th>حالة الخطة</th><th>تاريخ البيانات</th><th>أنشأه</th><th>التنزيلات</th></tr></thead>
            <tbody>
            @foreach ($exports as $e)
                <tr>
                    <td><a href="{{ route('exports.download', $e) }}">⬇ {{ $e->file_name }}</a><div class="small muted">{{ strtoupper($e->format) }} · {{ round($e->size / 1024) }} ك.ب</div></td>
                    <td class="small">{{ $e->plan?->position->name ?? 'جميع المناصب' }}</td>
                    <td class="small">{{ $e->scopeLabel() }}</td>
                    <td class="n">{{ $e->year->year }}{{ $e->quarter ? ' / ر' . $e->quarter : '' }}</td>
                    <td class="n">{{ $e->plan_id ? ($e->plan_version_no ? 'v' . $e->plan_version_no : 'مسودة') : '—' }}</td>
                    <td class="small">{{ $e->plan_status ? (in_array($e->plan_status, ['approved', 'active', 'closed']) ? 'معتمدة' : 'مسودة') : 'تقرير شامل' }}</td>
                    <td class="n">{{ Fmt::dt($e->data_as_of) }}</td>
                    <td class="small">{{ $e->user->name }}<br>{{ Fmt::dt($e->created_at) }}</td>
                    <td class="n">{{ $e->download_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $exports->links('partials.pager') }}
    @endif
</div>
@php
    $subjects = $plans->mapWithKeys(fn ($p) => [$p->id => [
        'objective' => $p->objectives->map(fn ($o) => ['id' => $o->id, 'n' => $o->title])->values(),
        'indicator' => $p->objectives->flatMap->indicators->map(fn ($i) => ['id' => $i->id, 'n' => $i->name])->values(),
        'project' => $p->projects->map(fn ($x) => ['id' => $x->id, 'n' => $x->name])->values(),
    ]]);
@endphp
@push('scripts')
<script>
(function () {
    var S = @json($subjects), needsQ = @json(ExportService::NEEDS_QUARTER);
    var labels = { objective: 'الهدف', indicator: 'المؤشر', project: 'المشروع' };
    var plan = document.getElementById('r-plan'), scope = document.getElementById('r-scope'), fmt = document.getElementById('r-format'), sub = document.getElementById('r-sub');
    if (!plan) return;
    function sync() {
        var o = scope.selectedOptions[0], s = scope.value;
        fmt.innerHTML = o.dataset.formats.split(',').map(function (f) { return '<option value="' + f + '">' + f.toUpperCase() + '</option>'; }).join('');
        document.getElementById('r-q-wrap').style.display = needsQ.indexOf(s) >= 0 ? '' : 'none';
        var w = document.getElementById('r-sub-wrap');
        if (labels[s]) {
            w.style.display = ''; sub.disabled = false;
            document.getElementById('r-sub-label').textContent = labels[s];
            sub.innerHTML = (S[plan.value][s] || []).map(function (x) { var op = document.createElement('option'); op.value = x.id; op.textContent = x.n; return op.outerHTML; }).join('');
        } else { w.style.display = 'none'; sub.disabled = true; }
    }
    plan.addEventListener('change', sync); scope.addEventListener('change', sync); sync();
})();
</script>
@endpush
@endsection
