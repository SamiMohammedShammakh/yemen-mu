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
@if ($objective->description)<div class="card"><p>{!! nl2br(e($objective->description)) !!}</p></div>@endif
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
