@extends('layouts.app')
@section('title', 'لوحة ' . $position->name)
@php use App\Support\Fmt; use App\Support\Workspace; @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $position->name }}</h1>
    <span class="pill">{{ $ctx->year?->year ?? '—' }} · {{ Workspace::quarterName($q) }}</span>
    @if ($plan)<span class="badge {{ $plan->status }}">{{ $plan->statusLabel() }}</span><span class="badge outline">{{ $plan->versionLabel() }}</span>@endif
</div>

@if (! $ctx->year)
    <div class="empty">لا توجد سنة تخطيط بعد. يطلب مسؤول التخطيط والمتابعة إنشاء السنة أولًا.</div>
@elseif (! $plan)
    {{-- لا توجد خطة لهذا المنصب في السنة المختارة --}}
    <div class="card">
        <h2>خطتي السنوية {{ $ctx->year->year }}</h2>
        <p class="muted">لم تُنشأ خطة {{ $position->name }} لسنة {{ $ctx->year->year }} بعد. ابدأ بمسودة جديدة أو انسخ هيكل خطة سابقة.</p>
        <div class="row">
            <form method="post" action="{{ route('plans.store') }}" class="inline">@csrf
                <input type="hidden" name="position_id" value="{{ $position->id }}">
                <input type="hidden" name="planning_year_id" value="{{ $ctx->year->id }}">
                <button class="btn">إنشاء مسودة خطة {{ $ctx->year->year }}</button>
            </form>
            @foreach ($previousPlans as $pp)
                <form method="post" action="{{ route('plans.copy', $pp) }}" class="inline">@csrf
                    <input type="hidden" name="planning_year_id" value="{{ $ctx->year->id }}">
                    <button class="btn sec" data-confirm="سيُنسخ هيكل خطة {{ $pp->year->year }} (الأهداف والمؤشرات والمستهدفات والمشاريع والمهام) دون الإنجاز أو الأدلة أو الاعتماد. متابعة؟">نسخ هيكل خطة {{ $pp->year->year }}</button>
                </form>
            @endforeach
        </div>
        <p class="hint">النسخ يشمل الهيكل فقط؛ لا يُنسخ الإنجاز ولا الأدلة ولا حالة الاعتماد.</p>
    </div>
@else
    {{-- خطتي السنوية --}}
    <div class="card">
        <div class="card-head">
            <h2>خطتي السنوية</h2>
            <div class="row">
                <a class="btn sec sm" href="{{ route('plans.show', $plan) }}">فتح الخطة</a>
                @if (\App\Support\Access::canEditPlan(auth()->user(), $plan))<a class="btn sm" href="{{ route('plans.edit', $plan) }}">تعديل المسودة</a>@endif
            </div>
        </div>
        @include('partials.plan-steps', ['plan' => $plan])
        @if ($plan->status === 'returned')
            @php $ret = $plan->reviews()->where('action', 'return')->latest('id')->first(); @endphp
            <div class="flash warn" style="margin-top:.7rem"><b>أُعيدت الخطة للتعديل:</b> {{ $ret?->note }}</div>
        @endif
        @if ($results)
            <div class="grid g4" style="margin-top:1rem">
                <div class="kpi accent">@include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف المرحلي (' . Workspace::quarterName($q) . ')', 'v' => $results['period_achievement'], 'key' => $results['status']['key']])</div>
                <div class="kpi">@include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف السنوي', 'v' => $results['annual_achievement'], 'key' => 'on_track'])</div>
                <div class="kpi"><div class="lbl">الحالة</div><div style="margin-top:.35rem">@include('partials.status', ['s' => $results['status']])</div><div class="sub">{{ $results['source'] === 'snapshot' ? 'ربع مغلق — من لقطة الإقفال' : 'حساب مباشر من التحديثات المعتمدة' }}</div></div>
                <div class="kpi"><div class="lbl">مهام {{ Workspace::quarterName($q) }}</div><div class="val num">{{ $results['tasks']['done'] }} / {{ $results['tasks']['planned'] }}</div><div class="sub">منجزة معتمدة · {{ $results['tasks']['overdue'] }} متأخرة · {{ $results['tasks']['deferred'] }} منقولة</div></div>
            </div>
        @elseif ($plan->isEditable())
            <p class="muted" style="margin-top:.7rem">أكمل نطاق العمل والأهداف والمؤشرات، ثم أرسل الخطة لمراجعة التخطيط.</p>
        @endif
    </div>

    {{-- الأرباع الأربعة --}}
    <div class="card">
        <div class="card-head"><h2>الأرباع الأربعة</h2><span class="small muted">اضغط الربع لعرض خطته ونتائجه</span></div>
        <div class="quarters">
            @foreach ($plan->year->quarters as $qq)
                @php $qs = $quarterSummary[$qq->number] ?? null; @endphp
                <a class="qcard {{ $qq->number === $q ? 'on' : '' }}" href="{{ route('plans.quarter', [$plan, $qq->number]) }}">
                    <div class="h"><span>{{ $qq->label() }}</span>@if ($qq->isClosed())<span class="badge closed">مغلق</span>@else<span class="badge outline">مفتوح</span>@endif</div>
                    <div class="d"><span class="num">{{ Fmt::date($qq->starts_on) }}</span> – <span class="num">{{ Fmt::date($qq->ends_on) }}</span></div>
                    @if ($qs)
                        <div style="margin-top:.35rem">@include('partials.ach', ['label' => 'مقابل مستهدف الربع', 'v' => $qs['ach'], 'key' => $qs['status']['key'], 'empty' => 'لم يستحق بعد / لا بيانات'])</div>
                    @endif
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid g-2-1">
        <div>
            {{-- أهدافي ومؤشراتي --}}
            <div class="card">
                <div class="card-head"><h2>أهدافي ومؤشراتي</h2>
                    @if ($results)@include('partials.download', ['scope' => 'results_quarter', 'plan' => $plan, 'quarter' => $q, 'label' => 'نتائج الربع'])@endif
                </div>
                @if (! $results)
                    <div class="empty">لا توجد أهداف بعد. @if ($plan->isEditable())<a href="{{ route('plans.edit', $plan) }}">أضف أهدافك ومؤشراتك</a>@endif</div>
                @else
                    @foreach ($results['objectives'] as $o)
                        <details class="box" @if ($loop->first) open @endif>
                            <summary>{{ $o['title'] }} <span class="small muted">({{ Fmt::num($o['weight']) }}%)</span><span class="right">@include('partials.status', ['s' => $o['status']])</span></summary>
                            <div class="in">
                                <div class="grid g2" style="gap:.6rem;margin-bottom:.2rem">
                                    @include('partials.ach', ['label' => 'إنجاز الهدف مقابل المستهدف المرحلي', 'v' => $o['period_achievement'], 'key' => $o['status']['key']])
                                    @include('partials.ach', ['label' => 'إنجاز الهدف مقابل المستهدف السنوي', 'v' => $o['annual_achievement'], 'key' => 'on_track'])
                                </div>
                                @foreach ($o['indicators'] as $i) @include('partials.indicator-tile', ['i' => $i]) @endforeach
                            </div>
                        </details>
                    @endforeach
                @endif
            </div>

            {{-- مشاريعي ومهامي --}}
            <div class="card">
                <div class="card-head"><h2>مشاريعي ومهامي — {{ Workspace::quarterName($q) }}</h2><a class="small" href="{{ route('plans.show', [$plan, 'tab' => 'tasks']) }}">كل المهام</a></div>
                @if ($projects->isNotEmpty())
                    <div class="row" style="margin-bottom:.6rem">
                        @foreach ($projects as $p)<a class="badge outline" href="{{ route('projects.show', $p) }}">{{ $p->typeLabel() }}: {{ $p->name }} ({{ $p->tasks_count }})</a>@endforeach
                    </div>
                @endif
                @if (empty($tasks))
                    <div class="empty">لا توجد مهام في هذا الربع.</div>
                @else
                    <div class="table-wrap"><table class="t">
                        <thead><tr><th>المهمة</th><th>المشروع</th><th>الموعد</th><th>الحالة</th></tr></thead>
                        <tbody>
                        @foreach ($tasks as $t)
                            <tr>
                                <td>{{ $t['title'] }}@if ($t['deferral_reason'])<div class="small muted">سبب النقل: {{ $t['deferral_reason'] }} — الموعد الجديد {{ $t['new_due_on'] }}</div>@endif</td>
                                <td class="small">{{ $t['project'] ?? '—' }}</td>
                                <td class="n">{{ $t['due_on'] ?? '—' }}</td>
                                <td><span class="badge {{ $t['status'] }}">{{ $t['status_label'] }}</span>@if ($t['overdue'])<span class="badge overdue">متأخرة</span>@endif</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </div>
        </div>

        <div>
            {{-- التحديثات المطلوبة --}}
            <div class="card">
                <h2>التحديثات المطلوبة</h2>
                @if ($plan->acceptsUpdates())
                    @forelse ($required as $i)
                        <div class="row between" style="padding:.35rem 0;border-bottom:1px solid var(--line-2)">
                            <span>{{ $i['name'] }}<br><span class="badge missing">بيانات ناقصة — {{ $i['period']['compare_label'] }}</span></span>
                            <a class="btn sm" href="{{ route('indicators.show', [$i['id'], 'quarter' => $q]) }}#update">تحديث</a>
                        </div>
                    @empty
                        <p class="muted small">لا توجد تحديثات مستحقة ناقصة في هذا الربع.</p>
                    @endforelse
                    @if ($pending->isNotEmpty())
                        <p class="small" style="margin-top:.6rem"><span class="badge pending">{{ $pending->count() }}</span> تحديث بانتظار تحقق مسؤول التخطيط (غير محتسب بعد).</p>
                    @endif
                    @foreach ($returned as $r)
                        <div class="flash warn small" style="margin:.4rem 0">أُعيد التحديث #{{ $r->id }} ({{ $r->indicator?->name ?? $r->task?->title }}): {{ $r->review_note }}</div>
                    @endforeach
                @else
                    <p class="muted small">تبدأ التحديثات بعد اعتماد الخطة.</p>
                @endif
            </div>

            {{-- الملاحظات والإجراءات التصحيحية --}}
            <div class="card">
                <div class="card-head"><h2>الملاحظات والإجراءات التصحيحية</h2><a class="small" href="{{ route('plans.show', [$plan, 'tab' => 'notes']) }}">الكل</a></div>
                @forelse ($actions as $a)
                    <div class="note">
                        <b>{{ $a->title }}</b> @if ($a->isOverdue())<span class="badge overdue">متأخر</span>@endif
                        <div class="meta">المالك: {{ $a->owner->name }} · الموعد {{ Fmt::date($a->due_on) }}</div>
                    </div>
                @empty
                    <p class="muted small">لا توجد إجراءات تصحيحية مفتوحة.</p>
                @endforelse
                @foreach ($notes as $n)
                    <div class="note"><div class="meta">{{ $n->author->name }} · {{ Fmt::dt($n->created_at) }}</div>{{ \Illuminate\Support\Str::limit($n->body, 160) }}
                        @if ($n->replies->isNotEmpty())<div class="small muted">{{ $n->replies->count() }} رد</div>@endif
                    </div>
                @endforeach
            </div>

            {{-- المرفقات والأدلة --}}
            <div class="card">
                <div class="card-head"><h2>المرفقات والأدلة</h2><a class="small" href="{{ route('plans.show', [$plan, 'tab' => 'files']) }}">الكل</a></div>
                @forelse ($attachments as $f)
                    <div class="small" style="padding:.2rem 0"><a href="{{ route('attachments.show', $f) }}">📎 {{ $f->original_name }}</a> <span class="muted">{{ $f->humanSize() }}</span></div>
                @empty
                    <p class="muted small">لا توجد مرفقات بعد.</p>
                @endforelse
            </div>

            {{-- تنزيل الخطة والنتائج --}}
            <div class="card">
                <h2>تنزيل الخطة والنتائج</h2>
                <div class="stack">
                    <div class="row between"><span class="small">الخطة كاملة</span>@include('partials.download', ['scope' => 'plan_full', 'plan' => $plan])</div>
                    <div class="row between"><span class="small">خطة {{ Workspace::quarterName($q) }}</span>@include('partials.download', ['scope' => 'plan_quarter', 'plan' => $plan, 'quarter' => $q])</div>
                    @if ($results)
                        <div class="row between"><span class="small">نتائج {{ Workspace::quarterName($q) }}</span>@include('partials.download', ['scope' => 'results_quarter', 'plan' => $plan, 'quarter' => $q])</div>
                        <div class="row between"><span class="small">النتائج السنوية حتى {{ Workspace::quarterName($q) }}</span>@include('partials.download', ['scope' => 'results_annual', 'plan' => $plan, 'quarter' => $q])</div>
                        <div class="row between"><span class="small">حزمة السنة كاملة</span>@include('partials.download', ['scope' => 'year_package', 'plan' => $plan])</div>
                    @endif
                </div>
                @if ($exports->isNotEmpty())
                    <hr><div class="small muted">آخر ملفاتي</div>
                    @foreach ($exports as $e)
                        <div class="small"><a href="{{ route('exports.download', $e) }}">{{ $e->file_name }}</a> <span class="muted">{{ Fmt::dt($e->created_at) }}</span></div>
                    @endforeach
                @endif
            </div>
        </div>
    </div>
@endif
@endsection
