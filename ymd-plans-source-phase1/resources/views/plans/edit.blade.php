@extends('layouts.app')
@section('title', 'إدخال الخطة — ' . $plan->title())
@php use App\Support\Fmt; $wsum = $plan->objectives->sum(fn ($o) => (float) $o->weight); $users = \App\Support\Access::usersHoldingPosition($plan->position_id); @endphp
@section('content')
@include('plans._header')

<div class="row" style="margin-bottom:1rem">
    <a class="btn sec" href="{{ route('plans.show', $plan) }}">عرض الخطة</a>
    <a class="btn" href="{{ route('plans.review', $plan) }}">مراجعة الاكتمال والإرسال</a>
    <span class="right small {{ $errors_list ? '' : 'muted' }}">
        @if ($errors_list)<span class="badge warn">{{ count($errors_list) }} حقل يحتاج استكمالًا</span>@else<span class="badge ok">الخطة مكتملة وجاهزة للإرسال</span>@endif
    </span>
</div>

<div class="card">
    <h2>1. بيانات الخطة</h2>
    <form method="post" action="{{ route('plans.update', $plan) }}">@csrf @method('put')
        <div class="form-grid">
            <div class="field full"><label class="f">وصف نطاق العمل <span style="color:var(--bad)">*</span></label><textarea name="scope_description" rows="3">{{ old('scope_description', $plan->scope_description) }}</textarea></div>
            <div class="field full"><label class="f">النتيجة العامة المتوقعة <span style="color:var(--bad)">*</span></label><textarea name="overall_outcome" rows="2">{{ old('overall_outcome', $plan->overall_outcome) }}</textarea></div>
            <div class="field"><label class="f">المخاطر</label><textarea name="risks" rows="3">{{ old('risks', $plan->risks) }}</textarea></div>
            <div class="field"><label class="f">الموارد</label><textarea name="resources" rows="3">{{ old('resources', $plan->resources) }}</textarea></div>
        </div>
        <button class="btn">حفظ بيانات الخطة</button>
    </form>
</div>

<div class="card">
    <div class="card-head">
        <h2>2. الأهداف التفصيلية وأوزانها</h2>
        <span class="badge {{ abs($wsum - 100) < 0.01 ? 'ok' : 'warn' }}">مجموع الأوزان: <span class="num">{{ Fmt::num($wsum) }}%</span> من 100%</span>
    </div>
    <p class="small muted">يجب أن يساوي مجموع أوزان الأهداف 100%، ولكل هدف مؤشر واحد قابل للقياس على الأقل. إن حددت أوزانًا للمؤشرات داخل الهدف فيجب أن يكون مجموعها 100%.</p>

    @foreach ($plan->objectives as $o)
        @php $iw = $o->indicators->whereNotNull('weight'); $isum = $o->indicators->sum('weight'); @endphp
        <details class="box" id="obj-{{ $o->id }}" open>
            <summary><span class="num small muted">{{ $o->ref }}</span> {{ $o->title }} <span class="small muted">— الوزن {{ Fmt::num($o->weight) }}%</span>
                @if ($o->strategicGoal)<span class="badge info">{{ $o->strategicGoal->ref }}</span>@elseif ($goals->isNotEmpty())<span class="badge warn">غير مرتبط بهدف استراتيجي</span>@endif
                @if ($o->indicators->isEmpty())<span class="badge warn">بلا مؤشر</span>@endif
                @if ($iw->isNotEmpty())<span class="badge {{ abs($isum - 100) < 0.01 && $iw->count() === $o->indicators->count() ? 'ok' : 'warn' }}">أوزان المؤشرات {{ Fmt::num($isum) }}%</span>@endif
            </summary>
            <div class="in">
                <form method="post" action="{{ route('objectives.update', $o) }}">@csrf @method('put')
                    <div class="form-grid g4">
                        <div class="field" style="grid-column: span 2"><label class="f">عنوان الهدف</label><input type="text" name="title" value="{{ $o->title }}" required></div>
                        <div class="field"><label class="f">الوزن %</label><input type="number" step="0.01" min="0" max="100" name="weight" value="{{ $o->weight }}"></div>
                        <div class="field" style="align-self:end"><button class="btn sec sm">حفظ الهدف</button></div>
                        <div class="field full"><label class="f">وصف الهدف</label><textarea name="description" rows="2">{{ $o->description }}</textarea></div>
                        <div class="field full"><label class="f">الهدف الاستراتيجي للجمعية</label>
                            <select name="strategic_goal_id">
                                <option value="">— غير مرتبط —</option>
                                @foreach ($goals as $g)<option value="{{ $g->id }}" @selected($o->strategic_goal_id == $g->id)>{{ $g->label() }}</option>@endforeach
                                @if ($o->strategicGoal && ! $goals->contains('id', $o->strategic_goal_id))<option value="{{ $o->strategic_goal_id }}" selected>{{ $o->strategicGoal->label() }} (مؤرشف)</option>@endif
                            </select>
                            @if ($goals->isEmpty())<div class="hint">لا توجد أهداف استراتيجية فعّالة لسنة الخطة بعد؛ يضيفها الرئيس أو مسؤولة التخطيط من «الأهداف الاستراتيجية».</div>@endif
                        </div>
                    </div>
                </form>

                <div class="table-wrap" style="margin:.4rem 0 .6rem"><table class="t">
                    <thead><tr><th>المؤشر</th><th>النوع</th><th>الاتجاه</th><th>خط الأساس</th><th>المستهدف السنوي</th><th>ر1</th><th>ر2</th><th>ر3</th><th>ر4</th><th>الوزن</th><th></th></tr></thead>
                    <tbody>
                    @forelse ($o->indicators as $i)
                        <tr>
                            <td><b>{{ $i->name }}</b><div class="small muted">{{ $i->unit }} · {{ \App\Models\Indicator::FREQUENCIES[$i->frequency] ?? 'دورية غير محددة' }} · {{ $i->data_source ?: 'مصدر البيانات غير محدد' }}</div></td>
                            <td class="small">{{ $i->kindLabel() }}</td>
                            <td class="small">{{ $i->directionLabel() }}</td>
                            <td class="n">{{ Fmt::num($i->baseline) }}</td>
                            <td class="n">{{ Fmt::num($i->annual_target) }}</td>
                            @for ($k = 1; $k <= 4; $k++)<td class="n">{{ Fmt::num($i->targetFor($k)) }}</td>@endfor
                            <td class="n">{{ $i->weight !== null ? Fmt::num($i->weight) . '%' : '—' }}</td>
                            <td class="row">
                                <a class="btn sec sm" href="{{ route('indicators.edit', $i) }}">تعديل</a>
                                <form method="post" action="{{ route('indicators.destroy', $i) }}" class="inline">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف المؤشر «{{ $i->name }}»؟">حذف</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="muted">لا يوجد مؤشر. لا يمكن إرسال الخطة قبل إضافة مؤشر قابل للقياس لهذا الهدف.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
                <div class="row">
                    <a class="btn sm" href="{{ route('indicators.create', [$plan, 'objective' => $o->id]) }}">+ إضافة مؤشر لهذا الهدف</a>
                    <form method="post" action="{{ route('objectives.destroy', $o) }}" class="inline right">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف الهدف ومؤشراته؟">حذف الهدف</button></form>
                </div>
            </div>
        </details>
    @endforeach

    <form method="post" action="{{ route('objectives.store', $plan) }}" class="card" style="background:var(--bg);box-shadow:none">@csrf
        <h3>إضافة هدف</h3>
        <div class="form-grid g4">
            <div class="field" style="grid-column: span 2"><label class="f">عنوان الهدف</label><input type="text" name="title" required></div>
            <div class="field"><label class="f">الوزن %</label><input type="number" step="0.01" min="0" max="100" name="weight" value="{{ max(0, 100 - $wsum) ?: '' }}"></div>
            <div class="field" style="align-self:end"><button class="btn sm">إضافة الهدف</button></div>
            <div class="field full"><label class="f">وصف الهدف</label><textarea name="description" rows="2"></textarea></div>
                        <div class="field full"><label class="f">الهدف الاستراتيجي للجمعية</label>
                            <select name="strategic_goal_id">
                                <option value="">— غير مرتبط —</option>
                                @foreach ($goals as $g)<option value="{{ $g->id }}" @selected(old('strategic_goal_id') == $g->id)>{{ $g->label() }}</option>@endforeach
                                
                            </select>
                            @if ($goals->isEmpty())<div class="hint">لا توجد أهداف استراتيجية فعّالة لسنة الخطة بعد؛ يضيفها الرئيس أو مسؤولة التخطيط من «الأهداف الاستراتيجية».</div>@endif
                        </div>
        </div>
    </form>
</div>

<div class="card" id="projects">
    <h2>3. المبادرات والأنشطة والمهام</h2>
    <p class="small muted">التسلسل: مبادرة أو مشروع ← أنشطة ← مهام، ولكل مستوى مسؤول. تُعطى الأرقام المرجعية تلقائيًا ولا تتغير.</p>
    @foreach ($plan->projects->whereNull('parent_id') as $p)
        <details class="box">
            <summary><span class="num small muted">{{ $p->ref }}</span> {{ $p->typeLabel() }}: {{ $p->name }}
                <span class="small muted">({{ $p->activities->count() }} نشاط · {{ $p->tasks()->count() }} مهمة مباشرة{{ $p->owner ? ' · المسؤول: ' . $p->owner->name : '' }})</span></summary>
            <div class="in">
                <form method="post" action="{{ route('projects.update', $p) }}">@csrf @method('put')
                    @include('plans._project-fields', ['p' => $p])
                    <div class="row"><button class="btn sec sm">حفظ {{ $p->typeLabel() }}</button></div>
                </form>
                <form method="post" action="{{ route('projects.destroy', $p) }}" style="margin-top:.4rem">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف {{ $p->typeLabel() }} {{ $p->ref }}؟ تبقى مهامه دون ارتباط.">حذف {{ $p->typeLabel() }}</button></form>

                <h3 style="margin-top:1rem">الأنشطة</h3>
                @foreach ($p->activities as $a)
                    <details class="box">
                        <summary class="small"><span class="num muted">{{ $a->ref }}</span> {{ $a->name }} <span class="muted">({{ $a->tasks()->count() }} مهمة{{ $a->owner ? ' · ' . $a->owner->name : '' }})</span></summary>
                        <div class="in">
                            <form method="post" action="{{ route('projects.update', $a) }}">@csrf @method('put')
                                @include('plans._activity-fields', ['a' => $a, 'parent' => $p])
                                <button class="btn sec sm">حفظ النشاط</button>
                            </form>
                            <form method="post" action="{{ route('projects.destroy', $a) }}" style="margin-top:.4rem">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف النشاط {{ $a->ref }}؟ تبقى مهامه دون ارتباط.">حذف النشاط</button></form>
                            <h3 style="margin-top:.8rem">مهام النشاط</h3>
                            @include('plans._tasks-editor', ['holder' => $a])
                        </div>
                    </details>
                @endforeach
                <form method="post" action="{{ route('projects.store', $plan) }}" class="card" style="background:var(--bg);box-shadow:none;margin-top:.5rem">@csrf
                    <h3>+ نشاط جديد ضمن {{ $p->ref }}</h3>
                    @include('plans._activity-fields', ['a' => null, 'parent' => $p])
                    <button class="btn sm">إضافة النشاط</button>
                </form>

                <h3 style="margin-top:1rem">مهام مباشرة (دون نشاط)</h3>
                @include('plans._tasks-editor', ['holder' => $p])
            </div>
        </details>
    @endforeach
    @php $loose = $plan->tasks()->whereNull('project_id')->get(); @endphp
    <details class="box">
        <summary>مهام عامة غير مرتبطة بمشروع <span class="small muted">({{ $loose->count() }})</span></summary>
        <div class="in">
            @foreach ($loose as $t)
                <details class="box"><summary class="small">ر{{ $t->quarter }} — {{ $t->title }}</summary>
                    <div class="in">
                        <form method="post" action="{{ route('tasks.update', $t) }}">@csrf @method('put')
                            @include('plans._task-fields', ['t' => $t, 'projectId' => null])
                            <button class="btn sec sm">حفظ المهمة</button>
                        </form>
                        <form method="post" action="{{ route('tasks.destroy', $t) }}" style="margin-top:.4rem">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف المهمة؟">حذف</button></form>
                    </div>
                </details>
            @endforeach
            <form method="post" action="{{ route('tasks.store', $plan) }}">@csrf
                @include('plans._task-fields', ['t' => null, 'projectId' => null])
                <button class="btn sm">إضافة مهمة عامة</button>
            </form>
        </div>
    </details>
    <form method="post" action="{{ route('projects.store', $plan) }}" class="card" style="background:var(--bg);box-shadow:none">@csrf
        <h3>إضافة مبادرة أو مشروع</h3>
        @include('plans._project-fields', ['p' => null])
        <button class="btn sm">إضافة</button>
    </form>
</div>
@endsection
