@extends('layouts.app')
@section('title', $plan->title() . ' — ' . \App\Support\Workspace::quarterName($q))
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
@include('plans._header')

<nav class="tabs">
    <a href="{{ route('plans.show', $plan) }}">← الخطة كاملة</a>
    @for ($k = 1; $k <= 4; $k++)
        <a class="{{ $k === $q ? 'on' : '' }}" href="{{ route('plans.quarter', [$plan, $k]) }}">{{ Workspace::quarterName($k) }}@if ($plan->year->quarter($k)?->isClosed()) 🔒@endif</a>
    @endfor
</nav>

<div class="card">
    <div class="card-head">
        <div>
            <h2>{{ Workspace::quarterName($q) }} {{ $plan->year->year }}</h2>
            <span class="small muted"><span class="num">{{ Fmt::date($quarter->starts_on) }}</span> – <span class="num">{{ Fmt::date($quarter->ends_on) }}</span> ·
                @if ($quarter->isClosed()) مغلق في {{ Fmt::dt($quarter->closed_at) }} — النتائج من لقطة الإقفال ولا تتغير إلا بطلب تغيير @else مفتوح — النتائج تُحسب مباشرة من التحديثات المعتمدة @endif</span>
        </div>
        <div class="row">
            @include('partials.download', ['scope' => 'plan_quarter', 'plan' => $plan, 'quarter' => $q, 'label' => 'خطة الربع'])
            @if ($results)@include('partials.download', ['scope' => 'results_quarter', 'plan' => $plan, 'quarter' => $q, 'label' => 'نتائج الربع'])@endif
        </div>
    </div>
    @if ($results)
        <div class="grid g4">
            @include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف المرحلي', 'v' => $results['period_achievement'], 'key' => $results['status']['key']])
            @include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف السنوي', 'v' => $results['annual_achievement'], 'key' => 'on_track'])
            <div class="ach"><span class="lbl">الحالة</span>@include('partials.status', ['s' => $results['status']])</div>
            <div class="ach"><span class="lbl">مهام الربع</span><span class="small">{{ $results['tasks']['done'] }}/{{ $results['tasks']['planned'] }} منجزة · {{ $results['tasks']['deferred'] }} منقولة · {{ $results['tasks']['overdue'] }} متأخرة</span></div>
        </div>
        @if ($results['source'] === 'snapshot')
            <p class="hint">لقطة الإقفال: مراجعة {{ $results['snapshot_revision'] ?? 1 }} · نسخة الخطة v{{ $results['version_no'] }} · قواعد الحالات نسخة {{ $results['rules']['version'] }}.</p>
        @endif
    @endif
</div>

@if ($results)
    <div class="card">
        <h2>مستهدفات الربع</h2>
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الهدف</th><th>المؤشر</th><th>النوع</th><th>مستهدف الربع (الفترة)</th><th>المستهدف المرحلي للمقارنة</th><th>المستهدف السنوي</th></tr></thead>
            <tbody>
            @foreach ($results['objectives'] as $o)
                @foreach ($o['indicators'] as $i)
                    <tr>
                        <td class="small">{{ $o['title'] }}</td>
                        <td>{{ $i['name'] }}</td>
                        <td class="small">{{ $i['kind_label'] }}</td>
                        <td class="n">{{ Fmt::num($i['period']['target']) }}</td>
                        <td><span class="small muted">{{ $i['period']['compare_label'] }}:</span> <span class="num">{{ Fmt::num($i['period']['compare_target']) }}</span></td>
                        <td class="n">{{ Fmt::num($i['annual']['target']) }}</td>
                    </tr>
                @endforeach
            @endforeach
            </tbody>
        </table></div>
    </div>
    @include('partials.results-table', ['results' => $results, 'downloadPlan' => $plan])
@endif

<div class="card">
    <h2>مهام {{ Workspace::quarterName($q) }}</h2>
    @if (empty($tasks))
        <p class="muted small">لا توجد مهام في هذا الربع.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المهمة</th><th>المشروع</th><th>المسؤول</th><th>الموعد</th><th>الحالة</th></tr></thead>
            <tbody>
            @foreach ($tasks as $t)
                <tr>
                    <td>{{ $t['title'] }}@if ($t['deferral_reason'])<div class="small" style="color:var(--bad)">سبب النقل: {{ $t['deferral_reason'] }} — الموعد الجديد {{ $t['new_due_on'] }}</div>@endif</td>
                    <td class="small">{{ $t['project'] ?? '—' }}</td>
                    <td class="small">{{ $t['responsible'] ?? '—' }}</td>
                    <td class="n">{{ $t['due_on'] ?? '—' }}</td>
                    <td><span class="badge {{ $t['status'] }}">{{ $t['status_label'] }}</span>@if ($t['overdue'] ?? false)<span class="badge overdue">متأخرة</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
