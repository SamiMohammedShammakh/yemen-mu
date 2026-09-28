@extends('layouts.app')
@section('title', 'السنوات والأرباع')
@php use App\Support\Fmt; use App\Support\Access; $u = auth()->user(); @endphp
@section('content')
<div class="ws-title"><h1>سنوات التخطيط والأرباع</h1></div>

<div class="grid g2">
    @if (Access::canManageYears($u))
    <div class="card">
        <h2>إنشاء سنة تخطيط جديدة</h2>
        <form method="post" action="{{ route('years.store') }}" class="row">@csrf
            <input type="number" name="year" min="2020" max="2100" value="{{ ($years->max('year') ?? now()->year) + 1 }}" style="max-width:140px" required aria-label="السنة">
            <button class="btn">إنشاء السنة بأرباعها الأربعة</button>
        </form>
        <p class="hint">تُنشأ الأرباع تلقائيًا: يناير–مارس، أبريل–يونيو، يوليو–سبتمبر، أكتوبر–ديسمبر. بعدها ينشئ كل مسؤول خطته أو ينسخ هيكل خطته السابقة.</p>
    </div>
    @endif
    @if (Access::canReview($u) || Access::isPresident($u))
    <div class="card">
        <h2>حدود حالات الإنجاز</h2>
        @php $cur = $rules->firstWhere('is_active', true); @endphp
        <form method="post" action="{{ route('rules.store') }}">@csrf
            <div class="form-grid">
                <div class="field"><label class="f">«يسير حسب الخطة» عند إنجاز ≥ %</label><input type="number" step="0.01" name="on_track_min" value="{{ $cur?->on_track_min ?? 90 }}" required></div>
                <div class="field"><label class="f">«يحتاج متابعة» عند إنجاز ≥ %</label><input type="number" step="0.01" name="follow_up_min" value="{{ $cur?->follow_up_min ?? 70 }}" required></div>
            </div>
            <button class="btn sec sm">حفظ نسخة قواعد جديدة</button>
        </form>
        <p class="hint">ما دون الحد الثاني «متأخر». تُحفظ نسخة القواعد المستخدمة في كل لقطة ربع وكل ملف مُصدَّر.</p>
        <div class="small">@foreach ($rules as $r)<div>نسخة {{ $r->version }}: ≥{{ Fmt::num($r->on_track_min) }}% / ≥{{ Fmt::num($r->follow_up_min) }}% @if ($r->is_active)<span class="badge ok">سارية</span>@endif <span class="muted">{{ Fmt::dt($r->created_at) }}</span></div>@endforeach</div>
    </div>
    @endif
</div>

@foreach ($years as $y)
    <div class="card">
        <div class="card-head"><h2>سنة {{ $y->year }}</h2><span class="small muted">{{ $y->plans->count() }} خطة</span></div>
        <div class="quarters">
            @foreach ($y->quarters as $qq)
                <div class="qcard">
                    <div class="h"><span>{{ $qq->label() }}</span>@if ($qq->isClosed())<span class="badge closed">مغلق</span>@else<span class="badge outline">مفتوح</span>@endif</div>
                    <div class="d"><span class="num">{{ Fmt::date($qq->starts_on) }}</span> – <span class="num">{{ Fmt::date($qq->ends_on) }}</span></div>
                    @if ($qq->isClosed())
                        <div class="small muted">أُقفل {{ Fmt::dt($qq->closed_at) }}</div>
                    @elseif (Access::canReview($u))
                        <form method="post" action="{{ route('quarters.close', [$y, $qq->number]) }}" style="margin-top:.4rem">@csrf
                            <button class="btn dark sm" data-confirm="إقفال {{ $qq->label() }} {{ $y->year }} يحفظ لقطة من الخطط والنتائج والاعتمادات، ولا تُعدّل نتائجه بعدها إلا بطلب تغيير. متابعة؟">إقفال الربع</button>
                        </form>
                    @endif
                </div>
            @endforeach
        </div>
        @if ($y->plans->isNotEmpty())
            <div class="row small" style="margin-top:.6rem">@foreach ($y->plans->sortBy('position.sort') as $p)<span class="badge {{ $p->status }}">{{ $p->position->name }}: {{ $p->statusLabel() }}</span>@endforeach</div>
        @endif
    </div>
@endforeach
@endsection
