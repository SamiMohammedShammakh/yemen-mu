@extends('layouts.app')
@section('title', $indicator->name)
@php use App\Support\Fmt; use App\Support\Workspace; use App\Models\Indicator; $p = $result['period'] ?? null; @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $indicator->name }}</h1>
    <span class="pill">{{ $plan->title() }} · {{ Workspace::quarterName($q) }}</span>
    @if ($result)@include('partials.status', ['s' => $result['status']])@endif
</div>
<div class="row" style="margin-bottom:1rem">
    <a class="btn sec sm" href="{{ route('plans.show', [$plan, 'quarter' => $q]) }}">← الخطة</a>
    <a class="btn sec sm" href="{{ route('objectives.show', [$indicator->objective, 'quarter' => $q]) }}">الهدف: {{ $indicator->objective->title }}</a>
    @if ($canChange && $closedQuarters->isNotEmpty())
        <a class="btn sec sm" href="{{ route('change-requests.create', [$plan, 'type' => 'closed_quarter_result', 'indicator' => $indicator->id]) }}">طلب تعديل نتيجة ربع مغلق</a>
    @endif
    <span class="right">@include('partials.download', ['scope' => 'indicator', 'plan' => $plan, 'subject' => $indicator->id, 'quarter' => $q, 'label' => 'نتيجة المؤشر'])</span>
</div>

@if ($result)
<div class="grid g4">
    <div class="kpi accent">
        <div class="lbl">{{ $p['compare_label'] }}</div>
        <div class="val num">{{ Fmt::num($p['compare_target']) }}</div>
        <div class="sub">{{ $indicator->unit }}</div>
    </div>
    <div class="kpi">
        <div class="lbl">الفعلي المعتمد{{ $indicator->kind === 'cumulative' ? ' (تراكمي)' : '' }}</div>
        @if ($p['state'] === 'measured')<div class="val num">{{ Fmt::num($p['compare_actual']) }}</div>@else<div style="margin:.4rem 0"><span class="badge {{ $p['state'] }}">{{ $p['state_label'] }}</span></div>@endif
        <div class="sub">{{ $p['pending_count'] ? $p['pending_count'] . ' تحديث بانتظار التحقق غير محتسب' : 'من التحديثات المعتمدة فقط' }}</div>
    </div>
    <div class="kpi">
        @include('partials.ach', ['label' => 'الإنجاز مقابل ' . $p['compare_label'], 'v' => $p['achievement'], 'key' => $result['status']['key'], 'empty' => $p['state_label']])
        <div class="sub">الفجوة عن {{ $p['compare_label'] }}: {{ $p['gap_text'] }}</div>
    </div>
    <div class="kpi">
        @include('partials.ach', ['label' => 'الإنجاز مقابل المستهدف السنوي (' . Fmt::num($result['annual']['target']) . ')', 'v' => $result['annual']['achievement'], 'key' => 'on_track', 'empty' => $result['annual']['state_label']])
        <div class="sub">{{ $result['annual']['explain'] ?: '—' }}</div>
    </div>
</div>

<div class="card" style="margin-top:1rem">
    <h2>تفاصيل الحساب ومصدر كل رقم</h2>
    <div class="explain"><ul>@foreach ($result['explain'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
</div>
@endif

<div class="card">
    <h2>الأرباع الأربعة</h2>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>الربع</th><th>مستهدف الربع</th><th>المستهدف المرحلي للمقارنة</th><th>الفعلي المعتمد</th><th>الفجوة</th><th>الإنجاز</th><th>حالة البيانات</th><th>المصدر</th></tr></thead>
        <tbody>
        @foreach ($rows as $n => $r)
            @continue(! $r)
            <tr class="{{ $n === $q ? 'sub' : '' }}">
                <td><b>{{ Workspace::quarterName($n) }}</b></td>
                <td class="n">{{ Fmt::num($r['target']) }}</td>
                <td><span class="small muted">{{ $r['compare_label'] }}:</span> <span class="num">{{ Fmt::num($r['compare_target']) }}</span></td>
                <td>@if ($r['state'] === 'measured')<span class="num">{{ Fmt::num($r['compare_actual']) }}</span>@if ($indicator->kind === 'cumulative')<div class="small muted">فعلي الربع {{ Fmt::num($r['actual']) }}</div>@endif @else — @endif</td>
                <td class="small">{{ $r['gap_text'] }}</td>
                <td class="small">@if ($r['achievement'] !== null)الإنجاز مقابل {{ $r['compare_label'] }}: <b class="num">{{ Fmt::pct($r['achievement']) }}</b>@else — @endif</td>
                <td><span class="badge {{ $r['state'] }}">{{ $r['state_label'] }}</span></td>
                <td class="small">{{ $r['source'] === 'snapshot' ? 'لقطة إقفال 🔒' : 'حساب مباشر' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="grid g2">
    <div class="card">
        <h2>تعريف المؤشر</h2>
        <table class="kv">
            <tr><th>التعريف</th><td>{{ $indicator->definition }}</td></tr>
            <tr><th>الوحدة</th><td>{{ $indicator->unit }}</td></tr>
            <tr><th>النوع</th><td>{{ $indicator->kindLabel() }} — {{ Indicator::AGGREGATIONS[$indicator->aggregation] ?? '' }}</td></tr>
            <tr><th>اتجاه التحسن</th><td>{{ $indicator->directionLabel() }}@if ($indicator->direction === 'range') ({{ Fmt::num($indicator->range_min) }} – {{ Fmt::num($indicator->range_max) }})@endif</td></tr>
            <tr><th>خط الأساس</th><td class="num">{{ Fmt::num($indicator->baseline) }}</td></tr>
            <tr><th>المستهدف السنوي</th><td class="num">{{ Fmt::num($indicator->annual_target) }}</td></tr>
            <tr><th>مصدر البيانات</th><td>{{ $indicator->data_source }}</td></tr>
            <tr><th>طريقة التحقق</th><td>{{ $indicator->verification_method }}</td></tr>
            <tr><th>الدورية</th><td>{{ Indicator::FREQUENCIES[$indicator->frequency] ?? '—' }}</td></tr>
            <tr><th>المالك</th><td>{{ $indicator->owner?->name }}</td></tr>
            <tr><th>الأدلة المطلوبة</th><td>{{ $indicator->required_evidence ?? '—' }}</td></tr>
        </table>
    </div>
    <div id="update">
        @if ($canUpdate)
            @include('partials.update-form', ['plan' => $plan, 'openQ' => $openQuarters, 'indicatorId' => $indicator->id, 'taskId' => null, 'lockIndicator' => $indicator])
        @else
            <div class="card"><h2>تحديث المؤشر</h2><p class="small muted">{{ $plan->acceptsUpdates() ? 'تحديث المؤشر متاح لصاحب المنصب.' : 'تُضاف التحديثات بعد اعتماد الخطة.' }}</p></div>
        @endif
    </div>
</div>

<div class="card">
    <h2>سجل تحديثات المؤشر</h2>
    @if ($updates->isEmpty())
        <p class="muted small">لا توجد تحديثات.</p>
    @else
        <ul class="timeline">
            @foreach ($updates as $x)
                <li>
                    <b>#{{ $x->id }} — {{ Workspace::quarterName($x->quarter) }}: <span class="num">{{ Fmt::num($x->actual_value) }}</span> {{ $indicator->unit }}</b>
                    @if ($x->participants)<span class="small muted">({{ $x->participants }} مشاركًا)</span>@endif
                    <span class="badge {{ $x->status }}">{{ $x->statusLabel() }}</span>@if ($x->is_adjustment)<span class="badge warn">تسوية بطلب تغيير</span>@endif
                    <span class="small">{{ $x->status === 'approved' ? 'محتسب' : 'غير محتسب' }}</span>
                    @if ($x->achieved)<div class="small">{{ $x->achieved }}</div>@endif
                    @if ($x->delay_reason)<div class="small">سبب التأخير: {{ $x->delay_reason }}</div>@endif
                    @foreach ($x->attachments as $a)<div class="small"><a href="{{ route('attachments.show', $a) }}">📎 {{ $a->original_name }}</a></div>@endforeach
                    @if ($x->review_note)<div class="small muted">المراجعة: {{ $x->review_note }}</div>@endif
                    <div class="when">{{ $x->creator->name }} · {{ Fmt::dt($x->created_at) }}@if ($x->reviewer) — راجعه {{ $x->reviewer->name }} {{ Fmt::dt($x->reviewed_at) }}@endif</div>
                    @if (\App\Support\Access::canReview(auth()->user()) && $x->status === 'pending')@include('partials.review-form', ['update' => $x])@endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection
