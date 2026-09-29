@extends('layouts.app')
@section('title', $project->ref . ' — ' . $project->name)
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title">
    <h1><span class="num">{{ $project->ref }}</span> {{ $project->typeLabel() }}: {{ $project->name }}</h1>
    <span class="pill">{{ $plan->title() }}</span>
</div>
<div class="row" style="margin-bottom:1rem">
    <a class="btn sec sm" href="{{ route('plans.show', [$plan, 'tab' => 'tasks']) }}">← مهام الخطة</a>
    @if ($project->parent)<a class="btn sec sm" href="{{ route('projects.show', $project->parent) }}">↑ {{ $project->parent->typeLabel() }} {{ $project->parent->ref }}</a>@endif
    <span class="right">@include('partials.download', ['scope' => 'project', 'plan' => $plan, 'subject' => $project->id, 'label' => $project->typeLabel() . ' ومهامه'])</span>
</div>
<div class="card">
    <table class="kv">
        <tr><th>الرقم المرجعي</th><td class="num">{{ $project->ref }}</td></tr>
        @if ($project->parent)<tr><th>ضمن</th><td><a href="{{ route('projects.show', $project->parent) }}">{{ $project->parent->ref }} — {{ $project->parent->name }}</a></td></tr>@endif
        <tr><th>الهدف المرتبط</th><td>@if ($project->objective){{ $project->objective->ref }} — {{ $project->objective->title }}@else — @endif</td></tr>
        <tr><th>الهدف الاستراتيجي</th><td>{{ $project->objective?->strategicGoal?->label() ?? '—' }}</td></tr>
        <tr><th>المسؤول</th><td>{{ $project->owner?->name ?? '—' }}@if ($project->responsible) <span class="muted small">(المنفذ: {{ $project->responsible }})</span>@endif</td></tr>
        <tr><th>المدة</th><td><span class="num">{{ Fmt::date($project->starts_on) }}</span> – <span class="num">{{ Fmt::date($project->ends_on) }}</span></td></tr>
        <tr><th>الوصف</th><td>{{ $project->description ?? '—' }}</td></tr>
        @if (! $project->isActivity())<tr><th>الموارد</th><td>{{ $project->resources ?? '—' }}</td></tr>@endif
    </table>
</div>

@if (! $project->isActivity())
<div class="card">
    <h2>الأنشطة ({{ $project->activities->count() }})</h2>
    @if ($project->activities->isEmpty())
        <p class="muted small">لا توجد أنشطة.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الرقم</th><th>النشاط</th><th>المسؤول</th><th>المدة</th><th>المهام</th><th>منجزة</th></tr></thead>
            <tbody>
            @foreach ($project->activities as $a)
                <tr>
                    <td class="n">{{ $a->ref }}</td>
                    <td><a href="{{ route('projects.show', $a) }}">{{ $a->name }}</a></td>
                    <td class="small">{{ $a->owner?->name ?? $a->responsible ?? '—' }}</td>
                    <td class="small"><span class="num">{{ Fmt::date($a->starts_on) }}</span> – <span class="num">{{ Fmt::date($a->ends_on) }}</span></td>
                    <td class="n">{{ $a->tasks->count() }}</td>
                    <td class="n">{{ $a->tasks->where('status', 'done')->count() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endif

<div class="card">
    <h2>{{ $project->isActivity() ? 'مهام النشاط' : 'المهام المباشرة' }}</h2>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>الرقم</th><th>المهمة</th><th>الربع الأصلي</th><th>الربع الحالي</th><th>المسؤول</th><th>الموعد</th><th>الحالة</th><th>سجل النقل</th></tr></thead>
        <tbody>
        @forelse ($project->tasks as $t)
            <tr>
                <td class="n">{{ $t->ref }}</td>
                <td>{{ $t->title }}</td>
                <td>ر{{ $t->original_quarter }}</td>
                <td>ر{{ $t->quarter }}</td>
                <td class="small">{{ $t->owner?->name ?? $t->responsible ?? '—' }}</td>
                <td class="n">{{ Fmt::date($t->due_on) }}</td>
                <td><span class="badge {{ $t->status }}">{{ $t->statusLabel() }}</span>@if ($t->isOverdue())<span class="badge overdue">متأخرة</span>@endif</td>
                <td class="small">@foreach ($t->deferrals as $d)<div>ر{{ $d->from_quarter }} ← ر{{ $d->to_quarter }}: {{ $d->reason }}</div>@endforeach</td>
            </tr>
        @empty
            <tr><td colspan="8" class="muted">لا توجد مهام.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
