@extends('layouts.app')
@section('title', 'مراجعة الخطة واعتمادها')
@php use App\Support\Fmt; use App\Models\Indicator; @endphp
@section('content')
@include('plans._header')

<div class="grid g-2-1">
    <div>
        <div class="card">
            <div class="card-head"><h2>فحص اكتمال الخطة</h2>
                @if (! $errors_list)<span class="badge ok">مكتملة: الأهداف قابلة للقياس والأوزان 100% والمستهدفات الربعية مكتملة</span>@else<span class="badge warn">{{ count($errors_list) }} ملاحظة</span>@endif
            </div>
            @if ($errors_list)
                <p class="small muted">لا يمكن إرسال الخطة أو اعتمادها قبل استكمال الحقول التالية:</p>
                <ol class="small">
                    @foreach ($errors_list as $e)
                        <li>{{ $e['message'] }} @if ($e['url'] && \App\Support\Access::canEditPlan(auth()->user(), $plan))<a href="{{ $e['url'] }}">استكمال</a>@endif</li>
                    @endforeach
                </ol>
            @endif
        </div>

        <div class="card">
            <h2>محتوى الخطة</h2>
            <table class="kv">
                <tr><th>نطاق العمل</th><td>{!! nl2br(e($plan->scope_description ?: '—')) !!}</td></tr>
                <tr><th>النتيجة العامة</th><td>{!! nl2br(e($plan->overall_outcome ?: '—')) !!}</td></tr>
                <tr><th>المخاطر</th><td>{!! nl2br(e($plan->risks ?: '—')) !!}</td></tr>
                <tr><th>الموارد</th><td>{!! nl2br(e($plan->resources ?: '—')) !!}</td></tr>
            </table>
            @foreach ($plan->objectives as $o)
                <h3 style="margin-top:1rem"><span class="num">{{ $o->ref }}</span> {{ $o->title }} — <span class="num">{{ Fmt::num($o->weight) }}%</span></h3>
                <p class="small">الهدف الاستراتيجي: @if ($o->strategicGoal)<a href="{{ route('strategic-goals.show', $o->strategicGoal) }}">{{ $o->strategicGoal->label() }}</a>@else<span class="muted">غير مرتبط</span>@endif</p>
                <div class="table-wrap"><table class="t">
                    <thead><tr><th>المؤشر والتعريف</th><th>الوحدة / النوع / الاتجاه</th><th>خط الأساس</th><th>السنوي</th><th>ر1</th><th>ر2</th><th>ر3</th><th>ر4</th><th>المصدر والتحقق</th><th>الدورية / المالك</th></tr></thead>
                    <tbody>
                    @foreach ($o->indicators as $i)
                        @php $cum = 0; @endphp
                        <tr>
                            <td><b>{{ $i->name }}</b><div class="small muted">{{ $i->definition }}</div>@if ($i->weight !== null)<span class="badge outline">وزن {{ Fmt::num($i->weight) }}%</span>@endif</td>
                            <td class="small">{{ $i->unit }}<br>{{ $i->kindLabel() }} · {{ Indicator::AGGREGATIONS[$i->aggregation] ?? '' }}<br>{{ $i->directionLabel() }}@if ($i->direction === 'range') ({{ Fmt::num($i->range_min) }}–{{ Fmt::num($i->range_max) }})@endif</td>
                            <td class="n">{{ Fmt::num($i->baseline) }}</td>
                            <td class="n"><b>{{ Fmt::num($i->annual_target) }}</b></td>
                            @for ($k = 1; $k <= 4; $k++)
                                @php $t = $i->targetFor($k); $cum += $t ?? 0; @endphp
                                <td class="n">{{ Fmt::num($t) }}@if ($i->kind === 'cumulative')<div class="small muted">تراكمي {{ Fmt::num($cum) }}</div>@endif</td>
                            @endfor
                            <td class="small">{{ $i->data_source ?: '—' }}<br><span class="muted">{{ $i->verification_method }}</span></td>
                            <td class="small">{{ Indicator::FREQUENCIES[$i->frequency] ?? '—' }}<br>{{ $i->owner?->name ?? '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endforeach
            <h3 style="margin-top:1rem">المشاريع والمهام</h3>
            @forelse ($plan->projects as $p)
                <div class="small"><b>{{ $p->typeLabel() }}: {{ $p->name }}</b> — {{ $p->tasks->count() }} مهمة ({{ $p->tasks->groupBy('quarter')->map->count()->map(fn ($c, $k) => 'ر' . $k . ': ' . $c)->implode('، ') }})</div>
            @empty
                <p class="small muted">لا توجد مشاريع.</p>
            @endforelse
        </div>
    </div>

    <div>
        <div class="card" style="border-color:var(--brand-soft)">
            <h2>الإجراء المطلوب</h2>
            @if (empty($actions))
                <p class="small muted">لا توجد إجراءات متاحة لك على الخطة في حالتها الحالية ({{ $plan->statusLabel() }}).</p>
            @endif
            @if (in_array('submit', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'submit']) }}">@csrf
                    <p class="small">سترسل الخطة إلى مسؤول التخطيط والمتابعة للمراجعة، ولن تستطيع تعديلها إلا إذا أُعيدت إليك.</p>
                    <button class="btn" @disabled($errors_list)>إرسال لمراجعة التخطيط</button>
                    @if ($errors_list)<p class="hint">استكمل الحقول الناقصة أولًا.</p>@endif
                </form>
            @endif
            @if (in_array('recommend', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'recommend']) }}" style="margin-bottom:.8rem">@csrf
                    <label class="f">ملاحظة التوصية (اختياري)</label>
                    <textarea name="note" rows="2"></textarea>
                    <button class="btn" style="margin-top:.4rem" @disabled($errors_list)>التوصية باعتماد الخطة</button>
                </form>
            @endif
            @if (in_array('approve', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'approve']) }}" style="margin-bottom:.8rem">@csrf
                    @if ($delegation)<div class="flash info small">تعتمد بصفتك مفوّضًا بموجب الوثيقة: <b>{{ $delegation->document_ref }}</b> (سارية حتى {{ $delegation->ends_on ? Fmt::date($delegation->ends_on) : 'إشعار آخر' }}).</div>@endif
                    <label class="f">ملاحظة الاعتماد (اختياري)</label>
                    <textarea name="note" rows="2"></textarea>
                    <button class="btn" style="margin-top:.4rem" @disabled($errors_list)>اعتماد الخطة (ستُحفظ النسخة v{{ $plan->versions()->max('version_no') + 1 }})</button>
                    <p class="hint">بعد الاعتماد لا تُعدّل الأهداف والأوزان والمستهدفات إلا بطلب تعديل موثّق.</p>
                </form>
            @endif
            @if (in_array('return', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'return']) }}" style="margin-bottom:.8rem">@csrf
                    <label class="f">سبب الإعادة للتعديل <span style="color:var(--bad)">*</span></label>
                    <textarea name="note" rows="3" required></textarea>
                    <button class="btn danger" style="margin-top:.4rem">إعادة الخطة للتعديل</button>
                </form>
            @endif
            @if (in_array('activate', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'activate']) }}" style="margin-bottom:.8rem">@csrf
                    <button class="btn">تفعيل الخطة (بدء التنفيذ)</button>
                </form>
            @endif
            @if (in_array('close', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'close']) }}" style="margin-bottom:.8rem">@csrf
                    <button class="btn dark" data-confirm="إغلاق الخطة يوقف التحديثات عليها. متابعة؟">إغلاق الخطة</button>
                </form>
            @endif
            @if (in_array('note', $actions))
                <form method="post" action="{{ route('plans.workflow', [$plan, 'note']) }}">@csrf
                    <label class="f">إضافة ملاحظة مراجعة لصاحب الخطة</label>
                    <textarea name="note" rows="2" required></textarea>
                    <button class="btn sec sm" style="margin-top:.4rem">إضافة الملاحظة</button>
                </form>
            @endif
        </div>

        <div class="card">
            <h2>سجل الاعتماد والملاحظات</h2>
            <ul class="timeline">
                @forelse ($reviews as $r)
                    <li><b>{{ $r->actionLabel() }}</b> — {{ $r->user->name }}
                        @if ($r->delegation)<span class="badge warn">بتفويض {{ $r->delegation->document_ref }}</span>@endif
                        @if ($r->note)<div class="small">{{ $r->note }}</div>@endif
                        <div class="when">{{ Fmt::dt($r->created_at) }}{{ $r->version_no ? ' · v' . $r->version_no : '' }}</div></li>
                @empty
                    <li class="muted small">لا يوجد سجل بعد.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
