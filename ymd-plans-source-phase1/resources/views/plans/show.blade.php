@extends('layouts.app')
@section('title', $plan->title())
@php
    use App\Support\Workspace;
    $tabs = ['overview' => 'الخطة والنتائج', 'tasks' => 'المشاريع والمهام', 'updates' => 'التحديثات', 'notes' => 'الملاحظات والإجراءات', 'files' => 'المرفقات والأدلة', 'downloads' => 'التنزيلات', 'history' => 'النسخ والسجل'];
@endphp
@section('content')
@include('plans._header')

<div class="row" style="margin-bottom:1rem">
    @if ($canEdit)<a class="btn" href="{{ route('plans.edit', $plan) }}">تعديل المسودة</a>@endif
    @if (array_intersect($actions, ['submit', 'recommend', 'return', 'approve', 'activate', 'close', 'note']))
        <a class="btn {{ $canEdit ? 'sec' : '' }}" href="{{ route('plans.review', $plan) }}">
            {{ in_array('submit', $actions) ? 'مراجعة الاكتمال والإرسال' : (in_array('approve', $actions) ? 'مراجعة واعتماد' : 'المراجعة ودورة الاعتماد') }}
        </a>
    @endif
    @if ($canRequestChange)<a class="btn sec" href="{{ route('change-requests.create', $plan) }}">طلب تعديل الخطة</a>@endif
    @if (! empty($laterYears) && $laterYears->isNotEmpty())
        <form method="post" action="{{ route('plans.copy', $plan) }}" class="dl-form">@csrf
            <select name="planning_year_id" aria-label="السنة الهدف">@foreach ($laterYears as $ly)<option value="{{ $ly->id }}">{{ $ly->year }}</option>@endforeach</select>
            <button class="btn sec sm" data-confirm="نسخ الهيكل فقط (دون الإنجاز والأدلة والاعتماد)؟">نسخ الهيكل إلى سنة جديدة</button>
        </form>
    @endif
    <span class="right">@include('partials.download', ['scope' => 'plan_full', 'plan' => $plan, 'label' => 'الخطة كاملة'])</span>
</div>

@if ($plan->isEditable() && $errors_list)
    <div class="flash warn"><b>الخطة غير مكتملة للإرسال ({{ count($errors_list) }} ملاحظة).</b> <a href="{{ route('plans.review', $plan) }}">عرض ما يحتاج استكمالًا</a></div>
@endif

<nav class="tabs">
    @foreach ($tabs as $k => $label)
        <a class="{{ $tab === $k ? 'on' : '' }}" href="{{ route('plans.show', [$plan, 'tab' => $k, 'quarter' => request('quarter')]) }}">{{ $label }}</a>
    @endforeach
</nav>

@include('plans.tabs.' . (isset($tabs[$tab]) ? $tab : 'overview'))
@endsection
