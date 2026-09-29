@extends('layouts.app')
@section('title', 'طلب تعديل')
@php use App\Support\Fmt; use App\Support\Workspace; $closed = $plan->year->quarters->where('status', 'closed')->pluck('number')->all(); @endphp
@section('content')
<div class="ws-title">
    <h1>{{ $type === 'plan_amendment' ? 'طلب تعديل الخطة المعتمدة' : 'طلب تعديل نتيجة ربع مغلق' }}</h1>
    <span class="pill">{{ $plan->title() }} · النسخة الحالية v{{ $plan->current_version }}</span>
</div>

<form method="post" action="{{ route('change-requests.store', $plan) }}">
    @csrf
    <input type="hidden" name="type" value="{{ $type }}">

    @if ($type === 'plan_amendment')
        <div class="flash info small">
            عدّل القيم المطلوبة فقط واترك الباقي كما هو. سيُعرض الطلب مع القيم القديمة والجديدة وأثره على الأرباع.
            الأرباع المغلقة ({{ $closed ? implode('، ', array_map(fn ($n) => 'ر' . $n, $closed)) : 'لا يوجد' }}) تحتفظ بنتائجها المحفوظة ولا تتغير تقاريرها السابقة.
            بعد الاعتماد تُحفظ نسخة جديدة من الخطة وتبقى النسخ السابقة.
        </div>
        @foreach ($plan->objectives as $o)
            <div class="card">
                <div class="form-grid g4">
                    <div class="field" style="grid-column: span 3"><label class="f">عنوان الهدف</label><input type="text" name="objectives[{{ $o->id }}][title]" value="{{ old('objectives.' . $o->id . '.title', $o->title) }}"></div>
                    <div class="field"><label class="f">وزن الهدف % <span class="muted">(الحالي {{ Fmt::num($o->weight) }})</span></label><input type="number" step="0.01" name="objectives[{{ $o->id }}][weight]" value="{{ old('objectives.' . $o->id . '.weight', $o->weight) }}"></div>
                    <div class="field full"><label class="f">الهدف الاستراتيجي</label>
                        <select name="objectives[{{ $o->id }}][strategic_goal_id]">
                            @if (! $o->strategic_goal_id)<option value="">— غير مرتبط —</option>@endif
                            @foreach ($goals as $g)<option value="{{ $g->id }}" @selected(old('objectives.' . $o->id . '.strategic_goal_id', $o->strategic_goal_id) == $g->id)>{{ $g->label() }}</option>@endforeach
                            @if ($o->strategicGoal && ! $goals->contains('id', $o->strategic_goal_id))<option value="{{ $o->strategic_goal_id }}" selected>{{ $o->strategicGoal->label() }} (مؤرشف)</option>@endif
                        </select></div>
                </div>
                <div class="table-wrap"><table class="t">
                    <thead><tr><th>المؤشر</th><th>خط الأساس</th><th>المستهدف السنوي</th>@for ($k = 1; $k <= 4; $k++)<th>ر{{ $k }}@if (in_array($k, $closed)) 🔒@endif</th>@endfor<th>الوزن</th><th>مصدر البيانات</th></tr></thead>
                    <tbody>
                    @foreach ($o->indicators as $i)
                        @php $n = 'indicators[' . $i->id . ']'; $on = 'indicators.' . $i->id . '.'; @endphp
                        <tr>
                            <td><b>{{ $i->name }}</b><div class="small muted">{{ $i->kindLabel() }}</div></td>
                            <td><input type="number" step="any" name="{{ $n }}[baseline]" value="{{ old($on . 'baseline', $i->baseline) }}" style="min-width:80px"></td>
                            <td><input type="number" step="any" name="{{ $n }}[annual_target]" value="{{ old($on . 'annual_target', $i->annual_target) }}" style="min-width:80px"></td>
                            @for ($k = 1; $k <= 4; $k++)
                                <td><input type="number" step="any" name="{{ $n }}[targets][{{ $k }}]" value="{{ old($on . 'targets.' . $k, $i->targetFor($k)) }}" style="min-width:70px" @if (in_array($k, $closed)) title="ربع مغلق: تغيير مستهدفه لا يغيّر نتيجته المحفوظة" @endif></td>
                            @endfor
                            <td><input type="number" step="0.01" name="{{ $n }}[weight]" value="{{ old($on . 'weight', $i->weight) }}" style="min-width:70px"></td>
                            <td><input type="text" name="{{ $n }}[data_source]" value="{{ old($on . 'data_source', $i->data_source) }}" style="min-width:140px"></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endforeach
    @else
        @php $res = new \App\Services\Results(); @endphp
        <div class="card">
            <h2>{{ $indicator->name }}</h2>
            <p class="small muted">التعديل يُنشئ «تحديث تسوية» معتمدًا ومراجعة جديدة للقطة الربع؛ تبقى اللقطة الأصلية محفوظة وتظهر القيم القديمة والجديدة في السجل.</p>
            @if (! $closed)
                <div class="flash warn">لا يوجد ربع مغلق في هذه السنة.</div>
            @else
            <input type="hidden" name="indicator_id" value="{{ $indicator->id }}">
            <div class="form-grid g3">
                <div class="field"><label class="f">الربع المغلق</label>
                    <select name="quarter">
                        @foreach ($closed as $n)
                            @php $row = $res->findIndicator($res->plan($plan, $n), $indicator->id); @endphp
                            <option value="{{ $n }}">{{ Workspace::quarterName($n) }} — القيمة المعتمدة الحالية: {{ $row && $row['period']['actual'] !== null ? Fmt::num($row['period']['actual']) : 'لا توجد' }}</option>
                        @endforeach
                    </select></div>
                <div class="field"><label class="f">القيمة الفعلية الصحيحة للربع</label><input type="number" step="any" name="new_value" value="{{ old('new_value') }}" required></div>
                <div class="field"><label class="f">عدد المشاركين (للمتوسط المرجح)</label><input type="number" min="0" name="participants" value="{{ old('participants') }}"></div>
            </div>
            @endif
        </div>
    @endif

    <div class="card">
        <div class="field"><label class="f">سبب التعديل <span style="color:var(--bad)">*</span></label><textarea name="reason" rows="3" required>{{ old('reason') }}</textarea></div>
        <button class="btn">تقديم طلب التعديل</button>
        <a class="btn sec" href="{{ url()->previous() }}">إلغاء</a>
    </div>
</form>
@endsection
