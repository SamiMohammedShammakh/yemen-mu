@extends('layouts.app')
@section('title', 'الخطط')
@php use App\Support\Access; $u = auth()->user(); @endphp
@section('content')
<div class="ws-title">
    <h1>{{ Access::hasGlobalView($u) ? 'جميع الخطط' : 'خطتي' }}</h1>
    <span class="pill">{{ $ctx->year?->year ?? '—' }}</span>
</div>
@if ($plans->isEmpty())
    <div class="empty">
        لا توجد خطط ظاهرة لك في سنة {{ $ctx->year?->year }}.
        @foreach (Access::positions($u) as $pos)
            @if ($ctx->year)
                <form method="post" action="{{ route('plans.store') }}" style="margin-top:.6rem">@csrf
                    <input type="hidden" name="position_id" value="{{ $pos->id }}"><input type="hidden" name="planning_year_id" value="{{ $ctx->year->id }}">
                    <button class="btn">إنشاء خطة {{ $pos->name }} {{ $ctx->year->year }}</button>
                </form>
            @endif
        @endforeach
    </div>
@else
    <div class="table-wrap"><table class="t">
        <thead><tr><th>الرقم</th><th>المنصب</th><th>مالك الخطة</th><th>الحالة</th><th>النسخة</th><th>آخر تحديث</th><th></th></tr></thead>
        <tbody>
        @foreach ($plans as $p)
            <tr>
                <td class="n">{{ $p->ref }}</td>
                <td><a href="{{ route('plans.show', $p) }}"><b>{{ $p->position->name }}</b></a></td>
                <td>{{ $p->owner->name }}</td>
                <td><span class="badge {{ $p->status }}">{{ $p->statusLabel() }}</span></td>
                <td class="n">{{ $p->current_version ? 'v' . $p->current_version : '—' }}</td>
                <td class="n">{{ \App\Support\Fmt::dt($p->updated_at) }}</td>
                <td class="row">@include('partials.download', ['scope' => 'plan_full', 'plan' => $p, 'label' => 'الخطة'])</td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @if (! Access::hasGlobalView($u))
        <p class="hint">تظهر هنا خطط منصبك فقط.</p>
    @endif
@endif
@endsection
