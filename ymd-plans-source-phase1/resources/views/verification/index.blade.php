@extends('layouts.app')
@section('title', 'التحقق من الأدلة')
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
<div class="ws-title"><h1>التحقق من الأدلة</h1><span class="pill">{{ $updates->count() }} بانتظار التحقق</span></div>
<p class="muted small">راجع الدليل ثم اعتمد التحديث ليدخل في الإنجاز الرسمي، أو أعده مع سبب واضح. إعلان الاكتمال لا يُحتسب قبل الاعتماد.</p>

@forelse ($updates as $u)
    <div class="card">
        <div class="card-head">
            <div>
                <h3>#{{ $u->id }} — {{ $u->plan->position->name }} {{ $u->plan->year->year }}</h3>
                <span class="small muted">{{ $u->indicator ? 'المؤشر' : 'المهمة' }}: <b>{{ $u->indicator?->name ?? $u->task?->title }}</b> · {{ Workspace::quarterName($u->quarter) }}{{ $u->period_label ? ' (' . $u->period_label . ')' : '' }} · أدخله {{ $u->creator->name }} في {{ Fmt::dt($u->created_at) }}</span>
            </div>
            <div class="row">
                @if ($u->claims_completion)<span class="badge info">يعلن اكتمال العمل</span>@endif
                @if ($u->created_at->lt(now()->subDays(3)))<span class="badge late">ينتظر منذ أكثر من 3 أيام</span>@endif
            </div>
        </div>
        <div class="grid g-2-1">
            <div>
                <table class="kv">
                    @if ($u->indicator)
                        <tr><th>القيمة الفعلية للفترة</th><td><b class="num">{{ Fmt::num($u->actual_value) }}</b> {{ $u->indicator->unit }}@if ($u->participants) — {{ $u->participants }} مشاركًا@endif</td></tr>
                        <tr><th>مستهدف الربع</th><td class="num">{{ Fmt::num($u->indicator->targetFor($u->quarter)) }}</td></tr>
                        <tr><th>مصدر البيانات / التحقق</th><td>{{ $u->indicator->data_source }} — {{ $u->indicator->verification_method }}</td></tr>
                        <tr><th>الأدلة المطلوبة</th><td>{{ $u->indicator->required_evidence ?? '—' }}</td></tr>
                    @else
                        <tr><th>الدليل المطلوب للمهمة</th><td>{{ $u->task?->required_evidence ?? '—' }}</td></tr>
                    @endif
                    @if ($u->achieved)<tr><th>ما تم إنجازه</th><td>{{ $u->achieved }}</td></tr>@endif
                    @if ($u->not_achieved)<tr><th>ما لم يتم</th><td>{{ $u->not_achieved }}</td></tr>@endif
                    @if ($u->delay_reason)<tr><th>سبب التأخير</th><td>{{ $u->delay_reason }}</td></tr>@endif
                    @if ($u->obstacles)<tr><th>العوائق</th><td>{{ $u->obstacles }}</td></tr>@endif
                    @if ($u->support_needed)<tr><th>الدعم المطلوب</th><td>{{ $u->support_needed }}</td></tr>@endif
                    <tr><th>المرفقات</th><td>
                        @forelse ($u->attachments as $a)<div><a href="{{ route('attachments.show', $a) }}">📎 {{ $a->original_name }}</a> <span class="small muted">{{ $a->humanSize() }}</span></div>
                        @empty<span class="badge warn">لا يوجد مرفق</span>@endforelse
                    </td></tr>
                </table>
            </div>
            <div>@include('partials.review-form', ['update' => $u])</div>
        </div>
    </div>
@empty
    <div class="empty">لا توجد تحديثات بانتظار التحقق.</div>
@endforelse

@if ($recent->isNotEmpty())
<div class="card">
    <h2>آخر قرارات التحقق</h2>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>#</th><th>المنصب</th><th>البند</th><th>القرار</th><th>الملاحظة</th><th>المراجع</th><th>التاريخ</th></tr></thead>
        <tbody>
        @foreach ($recent as $u)
            <tr><td class="n">#{{ $u->id }}</td><td>{{ $u->plan->position->name }}</td><td class="small">{{ $u->indicator?->name ?? $u->task?->title }}</td>
                <td><span class="badge {{ $u->status }}">{{ $u->statusLabel() }}</span></td><td class="small">{{ $u->review_note }}</td><td class="small">{{ $u->reviewer?->name }}</td><td class="n">{{ Fmt::dt($u->reviewed_at) }}</td></tr>
        @endforeach
        </tbody>
    </table></div>
</div>
@endif
@endsection
