@extends('layouts.app')
@section('title', 'إدارة ' . $year->displayName())
@php use App\Support\Fmt; $locked = $year->isClosed(); $anyClosed = $year->quarters->contains(fn ($q) => $q->isClosed()); @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $year->displayName() }}</h1>
    <span class="badge {{ ['draft' => 'draft', 'open' => 'active', 'closed' => 'closed'][$year->status] ?? '' }}">{{ $year->statusLabel() }}</span>
    <a class="btn sec sm right" href="{{ route('years.index') }}">← السنوات والأرباع</a>
</div>

<div class="grid g-2-1">
    <div class="card">
        <h2>بيانات السنة</h2>
        @if ($locked)
            <div class="flash info small">السنة مغلقة منذ {{ Fmt::dt($year->closed_at) }} ({{ $year->closer?->name }}). أعد فتحها بسبب موثق لتعديلها.</div>
        @endif
        <form method="post" action="{{ route('years.update', $year) }}">@csrf @method('put')
            <fieldset @disabled($locked) style="border:0;padding:0;margin:0">
            <div class="form-grid">
                <div class="field"><label class="f">السنة</label><input type="text" value="{{ $year->year }}" disabled></div>
                <div class="field"><label class="f" for="e-name">اسم السنة</label><input id="e-name" type="text" name="name" value="{{ old('name', $year->name) }}" required></div>
                <div class="field"><label class="f" for="e-s">تاريخ البداية</label><input id="e-s" type="date" name="starts_on" value="{{ old('starts_on', $year->starts_on?->toDateString()) }}" required @readonly($anyClosed)></div>
                <div class="field"><label class="f" for="e-e">تاريخ النهاية</label><input id="e-e" type="date" name="ends_on" value="{{ old('ends_on', $year->ends_on?->toDateString()) }}" required @readonly($anyClosed)>
                    @if ($anyClosed)<div class="hint">لا تُعدَّل التواريخ بعد إقفال أحد الأرباع.</div>@else<div class="hint">تغيير التواريخ يعيد حساب مواعيد الأرباع الأربعة.</div>@endif</div>
                <div class="field"><label class="f" for="e-o">المسؤول عن السنة</label>
                    <select id="e-o" name="owner_user_id"><option value="">—</option>@foreach ($users as $x)<option value="{{ $x->id }}" @selected(old('owner_user_id', $year->owner_user_id) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
                <div class="field"><label class="f" for="e-d">موعد اعتماد الخطط</label><input id="e-d" type="date" name="plans_due_on" value="{{ old('plans_due_on', $year->plans_due_on?->toDateString()) }}"></div>
                <div class="field full"><label class="f" for="e-desc">وصف أو توجهات السنة</label><textarea id="e-desc" name="description" rows="3">{{ old('description', $year->description) }}</textarea></div>
            </div>
            <button class="btn">حفظ</button>
            </fieldset>
        </form>
    </div>

    <div>
        <div class="card">
            <h2>حالة السنة</h2>
            <div class="steps" style="margin-bottom:.8rem">
                @foreach (\App\Models\PlanningYear::STATUSES as $k => $l)
                    <span class="{{ $year->status === $k ? 'cur' : '' }}">{{ $l }}</span>@if (! $loop->last)<i>←</i>@endif
                @endforeach
            </div>
            @if ($year->status === 'draft')
                <form method="post" action="{{ route('years.transition', [$year, 'activate']) }}">@csrf
                    <p class="small muted">السنة في طور التحضير: يمكن إعداد الخطط، ولا تُولَّد لها تنبيهات المتابعة حتى تُفعَّل.</p>
                    <button class="btn">تفعيل السنة</button>
                </form>
            @elseif ($year->status === 'open')
                <form method="post" action="{{ route('years.transition', [$year, 'close']) }}">@csrf
                    <p class="small muted">يتطلب إقفال الأرباع الأربعة أولًا. بعد الإغلاق لا تُنشأ خطط جديدة في هذه السنة ولا تُعدَّل بياناتها.</p>
                    <label class="f" for="c-r">ملاحظة الإغلاق (اختياري)</label>
                    <textarea id="c-r" name="reason" rows="2"></textarea>
                    <button class="btn dark" style="margin-top:.4rem" data-confirm="إغلاق {{ $year->displayName() }}؟">إغلاق السنة</button>
                </form>
            @else
                <form method="post" action="{{ route('years.transition', [$year, 'reopen']) }}">@csrf
                    <label class="f" for="r-r">سبب إعادة الفتح أو رقم القرار <span style="color:var(--bad)">*</span></label>
                    <textarea id="r-r" name="reason" rows="2" required></textarea>
                    <button class="btn danger" style="margin-top:.4rem">إعادة فتح السنة</button>
                    <p class="hint">يُسجَّل السبب ومن أعاد الفتح ووقته في سجل التدقيق. لقطات الأرباع المغلقة تبقى كما هي.</p>
                </form>
            @endif
        </div>

        <div class="card">
            <h2>الأرباع</h2>
            @foreach ($year->quarters as $q)
                <div class="row between small" style="padding:.2rem 0">
                    <span>{{ $q->label() }}: <span class="num">{{ Fmt::date($q->starts_on) }}</span> – <span class="num">{{ Fmt::date($q->ends_on) }}</span></span>
                    <span class="badge {{ $q->isClosed() ? 'closed' : 'outline' }}">{{ $q->isClosed() ? 'مغلق' : 'مفتوح' }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>

<div class="card">
    <h2>سجل تغييرات السنة</h2>
    <ul class="timeline">
        @forelse ($history as $h)
            <li><b>{{ ['year.create' => 'إنشاء', 'year.update' => 'تعديل', 'year.activate' => 'تفعيل', 'year.close' => 'إغلاق', 'year.reopen' => 'إعادة فتح'][$h->action] ?? $h->action }}</b> — {{ $h->user?->name ?? 'النظام' }}
                @if ($h->old_values)<div class="small"><span class="diff-old">{{ json_encode($h->old_values, JSON_UNESCAPED_UNICODE) }}</span></div>@endif
                @if ($h->new_values)<div class="small"><span class="diff-new">{{ json_encode($h->new_values, JSON_UNESCAPED_UNICODE) }}</span></div>@endif
                <div class="when">{{ Fmt::dt($h->created_at) }}</div></li>
        @empty
            <li class="muted small">لا توجد تغييرات مسجلة.</li>
        @endforelse
    </ul>
</div>
@endsection
