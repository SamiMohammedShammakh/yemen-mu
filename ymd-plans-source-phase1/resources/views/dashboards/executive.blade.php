@extends('layouts.app')
@section('title', 'لوحة الرئيس والإدارة التنفيذية')
@php use App\Support\Fmt; use App\Support\Workspace; $rules = $calc->rules(); @endphp
@section('content')
<div class="ws-title">
    <h1>لوحة الرئيس والإدارة التنفيذية</h1>
    <span class="pill">{{ $ctx->year?->year ?? '—' }} · {{ Workspace::quarterName($q) }}</span>
    @if ($ctx->year)<span class="right">@include('partials.download', ['scope' => 'org_summary', 'year' => $ctx->year->id, 'quarter' => $q, 'label' => 'تقرير أداء الجمعية'])</span>@endif
</div>

<div class="grid g4">
    <div class="kpi accent">
        @include('partials.ach', ['label' => 'متوسط إنجاز الخطط المعتمدة مقابل المستهدف المرحلي', 'v' => $org['period'], 'key' => $calc->statusFor($org['period'])['key']])
        <div class="sub">متوسط بسيط لـ {{ $org['measured'] }} خطة مقاسة؛ لكل خطة مستهدفاتها</div>
    </div>
    <div class="kpi">@include('partials.ach', ['label' => 'متوسط الإنجاز مقابل المستهدفات السنوية', 'v' => $org['annual'], 'key' => 'on_track'])<div class="sub">لـ {{ $org['annual_count'] }} خطة لديها بيانات معتمدة</div></div>
    <div class="kpi"><div class="lbl">الخطط المعتمدة/النشطة</div><div class="val num">{{ $org['active'] }} / {{ $org['plans'] }}</div><div class="sub">من الخطط المنشأة لسنة {{ $ctx->year?->year }}</div></div>
    <div class="kpi"><div class="lbl">قرارات بانتظارك</div><div class="val num">{{ $decisions['approve']->count() + $decisions['crs']->count() }}</div><div class="sub">اعتماد خطط وطلبات تعديل</div></div>
</div>

@if ($decisions['approve']->isNotEmpty() || $decisions['crs']->isNotEmpty())
<div class="card" style="margin-top:1rem;border-color:var(--brand-soft)">
    <h2>القرارات المطلوبة</h2>
    @foreach ($decisions['approve'] as $p)
        <div class="row between" style="padding:.35rem 0;border-bottom:1px solid var(--line-2)"><span><span class="badge recommended">موصى باعتمادها</span> {{ $p->title() }}</span><a class="btn sm" href="{{ route('plans.review', $p) }}">مراجعة واعتماد</a></div>
    @endforeach
    @foreach ($decisions['crs'] as $cr)
        <div class="row between" style="padding:.35rem 0;border-bottom:1px solid var(--line-2)"><span><span class="badge pending">طلب تعديل #{{ $cr->id }}</span> {{ $cr->typeLabel() }} — {{ $cr->plan->position->name }}</span><a class="btn sm" href="{{ route('change-requests.show', $cr) }}">البت في الطلب</a></div>
    @endforeach
</div>
@endif

<div class="card" style="margin-top:1rem">
    <div class="card-head"><h2>مقارنة المناصب — {{ Workspace::quarterName($q) }}</h2>
        <span class="small muted">حدود الحالات (نسخة {{ $rules['version'] }}): يسير حسب الخطة ≥ {{ Fmt::num($rules['on_track_min']) }}% · يحتاج متابعة ≥ {{ Fmt::num($rules['follow_up_min']) }}%</span></div>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المنصب</th><th>الخطة</th><th>الإنجاز مقابل المستهدف المرحلي</th><th>الإنجاز مقابل المستهدف السنوي</th><th>تغطية القياس</th><th>المهام</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach ($rows as $x)
            @php $p = $x['plan']; $r = $x['r']; @endphp
            <tr>
                <td><b>{{ $p->position->name }}</b></td>
                <td><span class="badge {{ $p->status }}">{{ $p->statusLabel() }}</span> <span class="small muted">{{ $p->versionLabel() }}</span></td>
                <td style="min-width:160px">@if ($r)@include('partials.ach', ['label' => 'مقابل مستهدف ' . Workspace::quarterName($q), 'v' => $r['period_achievement'], 'key' => $r['status']['key']])@else<span class="small muted">غير معتمدة بعد</span>@endif</td>
                <td style="min-width:140px">@if ($r)@include('partials.ach', ['label' => 'مقابل المستهدف السنوي', 'v' => $r['annual_achievement'], 'key' => 'on_track'])@endif</td>
                <td class="n">{{ $r ? Fmt::pct($r['coverage']) : '—' }}</td>
                <td class="small">@if ($r){{ $r['tasks']['done'] }}/{{ $r['tasks']['planned'] }} منجزة · {{ $r['tasks']['overdue'] }} متأخرة @endif</td>
                <td>@if ($r)@include('partials.status', ['s' => $r['status']])@endif</td>
                <td><a class="btn sec sm" href="{{ route('plans.show', [$p, 'quarter' => $q]) }}">فتح الخطة</a></td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    <p class="hint">يُحتسب الإنجاز من التحديثات المعتمدة فقط. الربع المغلق يُقرأ من لقطة إقفاله. لا تُدمج نتائج سنوات مختلفة.</p>
</div>

<div class="card">
    <h2>مؤشرات متأخرة على مستوى الجمعية</h2>
    @if ($late->isEmpty())
        <p class="muted small">لا توجد مؤشرات متأخرة في الربع المختار.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المنصب</th><th>المؤشر</th><th>أساس المقارنة</th><th>المستهدف</th><th>الفعلي المعتمد</th><th>الإنجاز</th></tr></thead>
            <tbody>
            @foreach ($late as $x)
                @php $i = $x['i']; @endphp
                <tr>
                    <td>{{ $x['plan']->position->name }}</td>
                    <td><a href="{{ route('indicators.show', [$i['id'], 'quarter' => $q]) }}">{{ $i['name'] }}</a></td>
                    <td class="small">{{ $i['period']['compare_label'] }}</td>
                    <td class="n">{{ Fmt::num($i['period']['compare_target']) }}</td>
                    <td class="n">{{ Fmt::num($i['period']['compare_actual']) }}</td>
                    <td class="small">الإنجاز مقابل {{ $i['period']['compare_label'] }}: <b class="num">{{ Fmt::pct($i['period']['achievement']) }}</b></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
