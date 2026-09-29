@extends('layouts.app')
@section('title', 'طلبات التعديل')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title"><h1>طلبات التعديل</h1></div>
<p class="small muted">بعد اعتماد الخطة لا تُعدّل الأهداف والأوزان والمستهدفات مباشرة، ولا تُعدّل نتائج الأرباع المغلقة إلا بطلب موثّق يحفظ القيم القديمة والجديدة. يُقدَّم الطلب من صفحة الخطة أو المؤشر.</p>
@if ($crs->isEmpty())
    <div class="empty">لا توجد طلبات تعديل.</div>
@else
    <div class="table-wrap"><table class="t">
        <thead><tr><th>#</th><th>الخطة</th><th>النوع</th><th>السبب</th><th>مقدم الطلب</th><th>الحالة</th><th>التاريخ</th></tr></thead>
        <tbody>
        @foreach ($crs as $cr)
            <tr>
                <td class="n"><a href="{{ route('change-requests.show', $cr) }}">#{{ $cr->id }}</a></td>
                <td>{{ $cr->plan->position->name }} {{ $cr->plan->year->year }}</td>
                <td class="small">{{ $cr->typeLabel() }}{{ $cr->quarter ? ' — ر' . $cr->quarter : '' }}</td>
                <td class="small">{{ \Illuminate\Support\Str::limit($cr->reason, 90) }}</td>
                <td class="small">{{ $cr->requester->name }}</td>
                <td><span class="badge {{ $cr->status }}">{{ $cr->statusLabel() }}</span>@if ($cr->resulting_version_no)<span class="badge outline">v{{ $cr->resulting_version_no }}</span>@endif</td>
                <td class="n">{{ Fmt::dt($cr->created_at) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    {{ $crs->links('partials.pager') }}
@endif
@endsection
