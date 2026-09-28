@extends('layouts.app')
@section('title', $plan->title() . ' — v' . $version->version_no)
@php use App\Support\Fmt; $s = $version->snapshot; @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $plan->title() }} — النسخة v{{ $version->version_no }}</h1>
    @if (! $version->effective_to)<span class="badge ok">النسخة السارية</span>@else<span class="badge closed">نسخة سابقة</span>@endif
</div>
<div class="card">
    <table class="kv">
        <tr><th>سبب النسخة</th><td>{{ $version->reason }}</td></tr>
        <tr><th>اعتمدها</th><td>{{ $version->approver?->name }}</td></tr>
        <tr><th>سارية</th><td>من {{ Fmt::dt($version->effective_from) }} {{ $version->effective_to ? 'حتى ' . Fmt::dt($version->effective_to) : '' }}</td></tr>
        @if ($version->change_request_id)<tr><th>طلب التعديل</th><td><a href="{{ route('change-requests.show', $version->change_request_id) }}">#{{ $version->change_request_id }}</a></td></tr>@endif
        <tr><th>نطاق العمل</th><td>{{ $s['plan']['scope_description'] ?? '—' }}</td></tr>
        <tr><th>النتيجة العامة</th><td>{{ $s['plan']['overall_outcome'] ?? '—' }}</td></tr>
    </table>
</div>
@foreach ($s['objectives'] ?? [] as $o)
    <div class="card">
        <h3>{{ $o['title'] }} — {{ Fmt::num($o['weight']) }}%</h3>
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المؤشر</th><th>النوع</th><th>خط الأساس</th><th>السنوي</th><th>ر1</th><th>ر2</th><th>ر3</th><th>ر4</th><th>الوزن</th><th>المصدر</th></tr></thead>
            <tbody>
            @foreach ($o['indicators'] as $i)
                <tr>
                    <td>{{ $i['name'] }}</td>
                    <td class="small">{{ \App\Models\Indicator::KINDS[$i['kind']] ?? $i['kind'] }}</td>
                    <td class="n">{{ Fmt::num($i['baseline']) }}</td>
                    <td class="n"><b>{{ Fmt::num($i['annual_target']) }}</b></td>
                    @for ($k = 1; $k <= 4; $k++)<td class="n">{{ Fmt::num($i['targets'][$k] ?? null) }}</td>@endfor
                    <td class="n">{{ $i['weight'] !== null ? Fmt::num($i['weight']) . '%' : '—' }}</td>
                    <td class="small">{{ $i['data_source'] }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endforeach
<a class="btn sec" href="{{ route('plans.show', [$plan, 'tab' => 'history']) }}">← سجل النسخ</a>
@endsection
