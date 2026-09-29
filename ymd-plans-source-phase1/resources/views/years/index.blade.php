@extends('layouts.app')
@section('title', 'السنوات والأرباع')
@php use App\Support\Fmt; use App\Support\Access; $u = auth()->user(); @endphp
@section('content')
<div class="ws-title"><h1>سنوات التخطيط والأرباع</h1></div>

<div class="grid g2">
    @if (Access::canManageYears($u))
    @php $ny = ($years->max('year') ?? now()->year) + 1; @endphp
    <div class="card">
        <h2>إنشاء سنة تخطيط جديدة</h2>
        <form method="post" action="{{ route('years.store') }}">@csrf
            <div class="form-grid">
                <div class="field"><label class="f" for="y-year">السنة</label><input id="y-year" type="number" name="year" min="2020" max="2100" value="{{ old('year', $ny) }}" required
                    oninput="var f=this.form; f.name.value='سنة التخطيط '+this.value; f.starts_on.value=this.value+'-01-01'; f.ends_on.value=this.value+'-12-31';"></div>
                <div class="field"><label class="f" for="y-name">اسم السنة</label><input id="y-name" type="text" name="name" value="{{ old('name', 'سنة التخطيط ' . $ny) }}" required></div>
                <div class="field"><label class="f" for="y-s">تاريخ البداية</label><input id="y-s" type="date" name="starts_on" value="{{ old('starts_on', $ny . '-01-01') }}" required></div>
                <div class="field"><label class="f" for="y-e">تاريخ النهاية</label><input id="y-e" type="date" name="ends_on" value="{{ old('ends_on', $ny . '-12-31') }}" required></div>
                <div class="field"><label class="f" for="y-o">المسؤول عن السنة</label>
                    <select id="y-o" name="owner_user_id"><option value="">—</option>@foreach ($users as $x)<option value="{{ $x->id }}" @selected(old('owner_user_id', $u->id) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
                <div class="field"><label class="f" for="y-d">موعد اعتماد الخطط</label><input id="y-d" type="date" name="plans_due_on" value="{{ old('plans_due_on') }}"></div>
                <div class="field"><label class="f" for="y-st">الحالة عند الإنشاء</label>
                    <select id="y-st" name="status"><option value="open" @selected(old('status', 'open') === 'open')>نشطة</option><option value="draft" @selected(old('status') === 'draft')>مسودة (للتحضير)</option></select></div>
                <div class="field full"><label class="f" for="y-desc">وصف أو توجهات السنة (اختياري)</label><textarea id="y-desc" name="description" rows="2">{{ old('description') }}</textarea></div>
            </div>
            <button class="btn">إنشاء السنة بأرباعها الأربعة</button>
        </form>
        <p class="hint">تُقسم السنة تلقائيًا إلى أربعة أرباع مدة كل منها ثلاثة أشهر من تاريخ البداية. بعدها ينشئ كل مسؤول خطته أو ينسخ هيكل خطته السابقة.</p>
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
        <div class="card-head">
            <div>
                <h2>{{ $y->displayName() }} <span class="badge {{ ['draft' => 'draft', 'open' => 'active', 'closed' => 'closed'][$y->status] ?? '' }}">{{ $y->statusLabel() }}</span></h2>
                <span class="small muted"><span class="num">{{ Fmt::date($y->starts_on) }}</span> – <span class="num">{{ Fmt::date($y->ends_on) }}</span>
                    · المسؤول: {{ $y->owner?->name ?? '—' }}
                    · موعد اعتماد الخطط: @if ($y->plans_due_on)<span class="num">{{ Fmt::date($y->plans_due_on) }}</span>@if ($y->plansOverdue() && ! $y->isClosed()) <span class="badge overdue">فات الموعد</span>@endif @else — @endif
                    · {{ $y->plans->count() }} خطة</span>
            </div>
            @if (Access::canManageYears($u))
                <div class="row">
                    <a class="btn sec sm" href="{{ route('years.edit', $y) }}">إدارة السنة</a>
                    @if ($y->status === 'draft')
                        <form method="post" action="{{ route('years.transition', [$y, 'activate']) }}" class="inline">@csrf<button class="btn sm" data-confirm="تفعيل {{ $y->displayName() }}؟">تفعيل السنة</button></form>
                    @endif
                </div>
            @endif
        </div>
        @if ($y->description)<p class="small">{{ $y->description }}</p>@endif
        <div class="quarters">
            @foreach ($y->quarters as $qq)
                <div class="qcard">
                    <div class="h"><span>{{ $qq->label() }}</span>@if ($qq->isClosed())<span class="badge closed">مغلق</span>@else<span class="badge outline">مفتوح</span>@endif</div>
                    <div class="d"><span class="num">{{ Fmt::date($qq->starts_on) }}</span> – <span class="num">{{ Fmt::date($qq->ends_on) }}</span></div>
                    @if ($qq->isClosed())
                        <div class="small muted">أُقفل {{ Fmt::dt($qq->closed_at) }}</div>
                    @elseif (Access::canReview($u) && ! $y->isClosed())
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
