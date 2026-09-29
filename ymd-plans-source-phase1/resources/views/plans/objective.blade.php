@extends('layouts.app')
@section('title', $objective->title)
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $objective->title }}</h1>
    <span class="pill">{{ $plan->title() }} · {{ Workspace::quarterName($q) }}</span>
</div>
<div class="row" style="margin-bottom:1rem">
    <a class="btn sec sm" href="{{ route('plans.show', [$plan, 'quarter' => $q]) }}">← الخطة</a>
    <span class="right">@include('partials.download', ['scope' => 'objective', 'plan' => $plan, 'subject' => $objective->id, 'quarter' => $q, 'label' => 'نتيجة الهدف'])</span>
</div>
<div class="card">
    <table class="kv">
        <tr><th>الرقم المرجعي</th><td class="num">{{ $objective->ref }}</td></tr>
        <tr><th>الهدف الاستراتيجي</th><td>@if ($objective->strategicGoal)<a href="{{ route('strategic-goals.show', $objective->strategicGoal) }}">{{ $objective->strategicGoal->label() }}</a>@else<span class="muted">غير مرتبط</span>@endif</td></tr>
        @if ($objective->description)<tr><th>الوصف</th><td>{!! nl2br(e($objective->description)) !!}</td></tr>@endif
    </table>
</div>
@if ($result)
    @include('partials.results-table', ['results' => ['objectives' => [$result], 'quarter' => $q], 'downloadPlan' => null])
    <div class="card">
        <h2>تفاصيل الحساب ومصدر كل رقم</h2>
        @foreach ($result['indicators'] as $i)
            <h3>{{ $i['name'] }}</h3>
            <div class="explain"><ul>@foreach ($i['explain'] as $line)<li>{{ $line }}</li>@endforeach</ul></div>
        @endforeach
        <p class="hint" style="margin-top:.6rem">{{ $result['explain'] }}.</p>
    </div>
@endif
@endsection
