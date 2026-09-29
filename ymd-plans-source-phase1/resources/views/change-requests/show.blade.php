@extends('layouts.app')
@section('title', 'طلب تعديل #' . $cr->id)
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
<div class="ws-title">
    <h1>طلب تعديل #{{ $cr->id }}</h1>
    <span class="badge {{ $cr->status }}">{{ $cr->statusLabel() }}</span>
    <span class="pill">{{ $cr->plan->title() }}</span>
</div>
<div class="grid g-2-1">
    <div>
        <div class="card">
            <table class="kv">
                <tr><th>النوع</th><td>{{ $cr->typeLabel() }}{{ $cr->quarter ? ' — ' . Workspace::quarterName($cr->quarter) : '' }}</td></tr>
                <tr><th>السبب</th><td>{!! nl2br(e($cr->reason)) !!}</td></tr>
                <tr><th>مقدم الطلب</th><td>{{ $cr->requester->name }} · {{ Fmt::dt($cr->created_at) }}</td></tr>
                <tr><th>النسخة وقت الطلب</th><td>v{{ $cr->base_version_no }}</td></tr>
                @if ($cr->decider)<tr><th>القرار</th><td>{{ $cr->statusLabel() }} — {{ $cr->decider->name }} · {{ Fmt::dt($cr->decided_at) }}@if ($cr->decision_note)<div class="small">{{ $cr->decision_note }}</div>@endif</td></tr>@endif
                @if ($cr->resulting_version_no)<tr><th>النسخة الناتجة</th><td><a href="{{ route('plans.version', [$cr->plan, $cr->resulting_version_no]) }}">v{{ $cr->resulting_version_no }}</a></td></tr>@endif
            </table>
        </div>
        <div class="card">
            <h2>القيم القديمة والجديدة</h2>
            <div class="table-wrap"><table class="t">
                <thead><tr><th>البند</th><th>القيمة القديمة</th><th>القيمة الجديدة</th></tr></thead>
                <tbody>
                @foreach ($cr->changes as $c)
                    <tr>
                        <td>{{ $c['label'] }}</td>
                        <td><span class="diff-old">{{ $c['old_label'] ?? (is_numeric($c['old']) ? Fmt::num($c['old']) : ($c['old'] ?? '—')) }}</span></td>
                        <td><span class="diff-new">{{ $c['new_label'] ?? (is_numeric($c['new']) ? Fmt::num($c['new']) : $c['new']) }}</span>@if (! empty($c['participants'])) <span class="small muted">({{ $c['participants'] }} مشاركًا)</span>@endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <div class="card">
            <h2>الأثر على الأرباع</h2>
            @forelse ($cr->impact ?? [] as $im)
                <div style="padding:.25rem 0"><b>{{ Workspace::quarterName($im['quarter']) }}</b> @if ($im['closed'])<span class="badge closed">مغلق</span>@else<span class="badge outline">مفتوح</span>@endif — <span class="small">{{ $im['note'] }}</span></div>
            @empty
                <p class="small muted">لا يؤثر على مستهدفات الأرباع (تعديل وصفي).</p>
            @endforelse
        </div>
    </div>
    <div>
        @if ($canDecide)
            <div class="card" style="border-color:var(--brand-soft)">
                <h2>القرار</h2>
                <form method="post" action="{{ route('change-requests.decide', $cr) }}">@csrf
                    <label class="f">ملاحظة القرار (إلزامية عند الرفض)</label>
                    <textarea name="decision_note" rows="3"></textarea>
                    <div class="row" style="margin-top:.5rem">
                        <button class="btn" name="decision" value="approve">اعتماد التعديل</button>
                        <button class="btn danger" name="decision" value="reject">رفض</button>
                    </div>
                    <p class="hint">{{ $cr->type === 'plan_amendment' ? 'الاعتماد يحفظ نسخة جديدة من الخطة ويبقي النسخ السابقة ولقطات الأرباع المغلقة كما هي.' : 'الاعتماد يحفظ مراجعة جديدة للقطة الربع مع بقاء الأصل.' }}</p>
                </form>
            </div>
        @endif
        <a class="btn sec" href="{{ route('plans.show', [$cr->plan, 'tab' => 'history']) }}">← سجل الخطة</a>
    </div>
</div>
@endsection
