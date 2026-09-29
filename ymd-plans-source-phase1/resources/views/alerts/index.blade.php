@extends('layouts.app')
@section('title', 'التنبيهات')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title"><h1>التنبيهات</h1>
    @if ($unreadAlerts)<form method="post" action="{{ route('alerts.readAll') }}" class="right">@csrf<button class="btn sec sm">تعليم الكل كمقروء</button></form>@endif
</div>
@if ($alerts->isEmpty())
    <div class="empty">لا توجد تنبيهات.</div>
@else
    <div class="card" style="padding:0">
        @foreach ($alerts as $a)
            <a href="{{ route('alerts.open', $a) }}" style="display:block;padding:.7rem 1rem;border-bottom:1px solid var(--line-2);color:inherit;{{ $a->read_at ? '' : 'background:var(--brand-50)' }}">
                <div class="row between"><b>{{ $a->title }}</b><span class="badge {{ in_array($a->type, ['task_overdue', 'below_target']) ? 'late' : (in_array($a->type, ['evidence_pending', 'update_due']) ? 'warn' : 'info') }}">{{ $a->typeLabel() }}</span></div>
                @if ($a->body)<div class="small">{{ $a->body }}</div>@endif
                <div class="small muted">{{ Fmt::dt($a->created_at) }}{{ $a->read_at ? '' : ' · جديد' }}</div>
            </a>
        @endforeach
    </div>
    {{ $alerts->links('partials.pager') }}
@endif
@endsection
