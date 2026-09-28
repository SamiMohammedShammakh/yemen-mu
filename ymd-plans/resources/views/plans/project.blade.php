@extends('layouts.app')
@section('title', $project->name)
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $project->typeLabel() }}: {{ $project->name }}</h1>
    <span class="pill">{{ $plan->title() }}</span>
</div>
<div class="row" style="margin-bottom:1rem">
    <a class="btn sec sm" href="{{ route('plans.show', [$plan, 'tab' => 'tasks']) }}">← مهام الخطة</a>
    <span class="right">@include('partials.download', ['scope' => 'project', 'plan' => $plan, 'subject' => $project->id, 'label' => 'المشروع ومهامه'])</span>
</div>
<div class="card">
    <table class="kv">
        <tr><th>الهدف المرتبط</th><td>{{ $project->objective?->title ?? '—' }}</td></tr>
        <tr><th>الوصف</th><td>{{ $project->description ?? '—' }}</td></tr>
        <tr><th>المسؤول</th><td>{{ $project->responsible ?? '—' }}</td></tr>
        <tr><th>المدة</th><td><span class="num">{{ Fmt::date($project->starts_on) }}</span> – <span class="num">{{ Fmt::date($project->ends_on) }}</span></td></tr>
        <tr><th>الموارد</th><td>{{ $project->resources ?? '—' }}</td></tr>
    </table>
</div>
<div class="card">
    <h2>المهام</h2>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المهمة</th><th>الربع الأصلي</th><th>الربع الحالي</th><th>المسؤول</th><th>الموعد</th><th>الحالة</th><th>سجل النقل</th></tr></thead>
        <tbody>
        @forelse ($project->tasks as $t)
            <tr>
                <td>{{ $t->title }}</td>
                <td>ر{{ $t->original_quarter }}</td>
                <td>ر{{ $t->quarter }}</td>
                <td class="small">{{ $t->responsible ?? $t->owner?->name }}</td>
                <td class="n">{{ Fmt::date($t->due_on) }}</td>
                <td><span class="badge {{ $t->status }}">{{ $t->statusLabel() }}</span>@if ($t->isOverdue())<span class="badge overdue">متأخرة</span>@endif</td>
                <td class="small">@foreach ($t->deferrals as $d)<div>ر{{ $d->from_quarter }} ← ر{{ $d->to_quarter }}: {{ $d->reason }}</div>@endforeach</td>
            </tr>
        @empty
            <tr><td colspan="7" class="muted">لا توجد مهام.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
