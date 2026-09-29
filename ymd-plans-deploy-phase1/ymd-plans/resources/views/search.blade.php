@extends('layouts.app')
@section('title', 'البحث')
@section('content')
<div class="ws-title"><h1>البحث</h1></div>
<form method="get" action="{{ route('search') }}" class="row" style="margin-bottom:1rem">
    <input type="search" name="q" value="{{ $term }}" placeholder="ابحث في الأهداف والمؤشرات والمشاريع والمهام" style="max-width:420px">
    <button class="btn">بحث</button>
</form>
<p class="hint">يقتصر البحث على الخطط التي تملك صلاحية رؤيتها.</p>
@if (mb_strlen(trim($term)) >= 2)
    @php $total = collect($results)->sum(fn ($c) => $c->count()); @endphp
    @if (! $total)
        <div class="empty">لا توجد نتائج لـ «{{ $term }}».</div>
    @endif
    @foreach (['objectives' => 'الأهداف', 'indicators' => 'المؤشرات', 'projects' => 'المشاريع', 'tasks' => 'المهام'] as $k => $label)
        @if ($results[$k]->isNotEmpty())
            <div class="card">
                <h2>{{ $label }} ({{ $results[$k]->count() }})</h2>
                @foreach ($results[$k] as $m)
                    @php
                        $url = match ($k) {
                            'objectives' => route('objectives.show', $m),
                            'indicators' => route('indicators.show', $m),
                            'projects' => route('projects.show', $m),
                            default => route('plans.show', [$m->plan_id, 'tab' => 'tasks']),
                        };
                    @endphp
                    <div style="padding:.25rem 0"><a href="{{ $url }}">{{ $m->title ?? $m->name }}</a> <span class="small muted">— {{ $m->plan->position->name }} {{ $m->plan->year->year }}</span></div>
                @endforeach
            </div>
        @endif
    @endforeach
@endif
@endsection
