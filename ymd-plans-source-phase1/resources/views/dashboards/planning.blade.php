@extends('layouts.app')
@section('title', 'مركز التخطيط والمتابعة')
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
<div class="ws-title">
    <h1>مركز التخطيط والمتابعة</h1>
    <span class="pill">{{ $ctx->year?->year ?? '—' }} · {{ Workspace::quarterName($q) }}</span>
</div>

<div class="grid g4">
    <div class="kpi accent"><div class="lbl">خطط بانتظار مراجعتي</div><div class="val num">{{ $toReview->count() }}</div><div class="sub">مرسلة لمراجعة التخطيط</div></div>
    <div class="kpi"><div class="lbl">أدلة بانتظار التحقق</div><div class="val num">{{ $pending->count() }}</div><div class="sub">لا تدخل في الإنجاز قبل اعتمادها</div></div>
    <div class="kpi"><div class="lbl">فجوات تحتاج متابعة</div><div class="val num">{{ count($gaps) }}</div><div class="sub">متأخر، يحتاج متابعة، أو بيانات ناقصة</div></div>
    <div class="kpi"><div class="lbl">إجراءات تصحيحية مفتوحة</div><div class="val num">{{ $actions->count() }}</div><div class="sub">{{ $actions->filter->isOverdue()->count() }} متأخرة عن موعدها</div></div>
</div>

<div class="card" style="margin-top:1rem">
    <div class="card-head">
        <h2>جميع الخطط — {{ Workspace::quarterName($q) }}</h2>
        @if ($ctx->year)@include('partials.download', ['scope' => 'org_summary', 'year' => $ctx->year->id, 'quarter' => $q, 'label' => 'تقرير شامل'])@endif
    </div>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المنصب</th><th>حالة الخطة</th><th>النسخة</th><th>الإنجاز مقابل المستهدف المرحلي</th><th>الإنجاز مقابل المستهدف السنوي</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach ($rows as $x)
            @php $p = $x['plan']; $r = $x['r']; @endphp
            <tr>
                <td><a href="{{ route('plans.show', [$p, 'quarter' => $q]) }}"><b>{{ $p->position->name }}</b></a><div class="small muted">{{ $p->owner->name }}</div></td>
                <td><span class="badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                <td class="n">{{ $p->current_version ? 'v' . $p->current_version : '—' }}</td>
                <td style="min-width:150px">@if ($r)@include('partials.ach', ['label' => 'مقابل مستهدف ' . Workspace::quarterName($q), 'v' => $r['period_achievement'], 'key' => $r['status']['key']])@else<span class="muted small">غير معتمدة</span>@endif</td>
                <td style="min-width:140px">@if ($r)@include('partials.ach', ['label' => 'مقابل المستهدف السنوي', 'v' => $r['annual_achievement'], 'key' => 'on_track'])@endif</td>
                <td>@if ($r)@include('partials.status', ['s' => $r['status']])@endif</td>
                <td>
                    @if (in_array($p->status, ['submitted', 'recommended']))<a class="btn sm" href="{{ route('plans.review', $p) }}">مراجعة</a>@endif
                </td>
            </tr>
        @endforeach
        @foreach ($missingPositions as $mp)
            <tr><td><b>{{ $mp->name }}</b></td><td colspan="6"><span class="badge missing">لم تُنشأ خطة لهذه السنة</span></td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>

<div class="grid g2">
    <div class="card">
        <div class="card-head"><h2>أدلة وتحديثات بانتظار التحقق</h2><a class="btn sm" href="{{ route('verification.index') }}">فتح قائمة التحقق</a></div>
        @forelse ($pending->take(8) as $u)
            <div class="row between" style="padding:.35rem 0;border-bottom:1px solid var(--line-2)">
                <span><b>{{ $u->plan->position->name }}</b> — {{ $u->indicator?->name ?? $u->task?->title }}<br>
                    <span class="small muted">#{{ $u->id }} · {{ Workspace::quarterName($u->quarter) }} · {{ Fmt::dt($u->created_at) }} · {{ $u->attachments->count() }} مرفق</span></span>
                @if ($u->created_at->lt(now()->subDays(3)))<span class="badge late">متأخر المراجعة</span>@endif
            </div>
        @empty
            <p class="muted small">لا توجد تحديثات بانتظار التحقق.</p>
        @endforelse
    </div>
    <div class="card">
        <h2>قرارات مراجعة الخطط</h2>
        @forelse ($toReview as $p)
            <div class="row between" style="padding:.35rem 0"><span>{{ $p->title() }}</span><a class="btn sm" href="{{ route('plans.review', $p) }}">مراجعة الخطة</a></div>
        @empty
            <p class="muted small">لا توجد خطط مرسلة للمراجعة.</p>
        @endforelse
        @if ($crs->isNotEmpty())
            <hr><div class="small muted">طلبات تعديل بانتظار قرار الرئيس</div>
            @foreach ($crs as $cr)<div class="small"><a href="{{ route('change-requests.show', $cr) }}">#{{ $cr->id }} {{ $cr->typeLabel() }} — {{ $cr->plan->position->name }}</a></div>@endforeach
        @endif
    </div>
</div>

<div class="card">
    <h2>الفجوات والمؤشرات التي تحتاج متابعة</h2>
    @if (empty($gaps))
        <p class="muted small">لا توجد فجوات في الربع المختار.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المنصب</th><th>المؤشر</th><th>أساس المقارنة</th><th>المستهدف</th><th>الفعلي المعتمد</th><th>الفجوة</th><th>الحالة</th><th></th></tr></thead>
            <tbody>
            @foreach ($gaps as $g)
                @php $i = $g['i']; $pp = $i['period']; @endphp
                <tr>
                    <td>{{ $g['plan']->position->name }}</td>
                    <td><a href="{{ route('indicators.show', [$i['id'], 'quarter' => $q]) }}">{{ $i['name'] }}</a></td>
                    <td class="small">{{ $pp['compare_label'] }}</td>
                    <td class="n">{{ Fmt::num($pp['compare_target']) }}</td>
                    <td>@if ($pp['state'] === 'measured')<span class="num">{{ Fmt::num($pp['compare_actual']) }}</span>@else<span class="badge {{ $pp['state'] }}">{{ $pp['state_label'] }}</span>@endif</td>
                    <td class="small">{{ $pp['gap_text'] }}</td>
                    <td>@include('partials.status', ['s' => $i['status']])</td>
                    <td><a class="btn sec sm" href="{{ route('plans.show', [$g['plan'], 'tab' => 'notes', 'indicator' => $i['id']]) }}">متابعة</a></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>

<div class="card">
    <h2>الإجراءات التصحيحية المفتوحة</h2>
    @if ($actions->isEmpty())
        <p class="muted small">لا توجد إجراءات مفتوحة.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المنصب</th><th>الإجراء</th><th>السبب</th><th>المالك</th><th>الموعد</th><th>النتيجة المتوقعة</th></tr></thead>
            <tbody>
            @foreach ($actions as $a)
                <tr>
                    <td>{{ $a->plan->position->name }}</td>
                    <td><a href="{{ route('plans.show', [$a->plan, 'tab' => 'notes']) }}">{{ $a->title }}</a></td>
                    <td class="small">{{ $a->reason }}</td>
                    <td>{{ $a->owner->name }}</td>
                    <td class="n">{{ Fmt::date($a->due_on) }} @if ($a->isOverdue())<span class="badge overdue">متأخر</span>@endif</td>
                    <td class="small">{{ $a->expected_result }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
