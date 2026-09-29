@extends('layouts.app')
@section('title', $indicator ? 'تعديل مؤشر' : 'إضافة مؤشر')
@php
    use App\Models\Indicator;
    $v = fn ($f, $d = null) => old($f, $indicator?->$f ?? $d);
    $objId = old('objective_id', $indicator?->objective_id ?? request('objective'));
    $tv = fn ($q) => old('targets.' . $q, $indicator?->targetFor($q));
    $err = fn ($f) => $errors->has($f) ? 'err' : '';
@endphp
@section('content')
<div class="ws-title">
    <h1>{{ $indicator ? 'تعديل المؤشر: ' . $indicator->name : 'إضافة مؤشر قابل للقياس' }}</h1>
    <span class="pill">{{ $plan->title() }}</span>
</div>
<form method="post" action="{{ $indicator ? route('indicators.update', $indicator) : route('indicators.store', $plan) }}" id="ind-form">
    @csrf @if ($indicator) @method('put') @endif
    <div class="card">
        <h2>تعريف المؤشر</h2>
        <div class="form-grid">
            <div class="field {{ $err('objective_id') }}"><label class="f">الهدف</label>
                <select name="objective_id" required>@foreach ($plan->objectives as $o)<option value="{{ $o->id }}" @selected($objId == $o->id)>{{ $o->title }}</option>@endforeach</select></div>
            <div class="field {{ $err('name') }}"><label class="f">اسم المؤشر *</label><input type="text" name="name" value="{{ $v('name') }}" required></div>
            <div class="field full {{ $err('definition') }}"><label class="f">التعريف *</label><textarea name="definition" rows="2" placeholder="ما الذي يُقاس بالضبط، وكيف يُحسب">{{ $v('definition') }}</textarea></div>
            <div class="field {{ $err('unit') }}"><label class="f">وحدة القياس *</label><input type="text" name="unit" value="{{ $v('unit') }}" placeholder="برنامج، مهندس، %، تقرير…"></div>
            <div class="field {{ $err('owner_user_id') }}"><label class="f">مالك المؤشر *</label>
                <select name="owner_user_id"><option value="">—</option>@foreach ($owners as $x)<option value="{{ $x->id }}" @selected($v('owner_user_id', auth()->id()) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
        </div>
    </div>

    <div class="card">
        <h2>طريقة القياس</h2>
        <div class="form-grid g3">
            <div class="field {{ $err('kind') }}"><label class="f">نوع المؤشر *</label>
                <select name="kind" id="kind">@foreach (Indicator::KINDS as $k => $l)<option value="{{ $k }}" @selected($v('kind', 'cumulative') === $k)>{{ $l }}</option>@endforeach</select>
                <div class="hint" id="kind-hint"></div></div>
            <div class="field {{ $err('aggregation') }}"><label class="f">طريقة التجميع السنوي *</label>
                <select name="aggregation" id="agg">@foreach (Indicator::AGGREGATIONS as $k => $l)<option value="{{ $k }}" @selected($v('aggregation', 'sum') === $k)>{{ $l }}</option>@endforeach</select>
                <div class="hint">التراكمي يُجمع دائمًا. القيمة في نقطة زمنية لا تُجمع نسبها.</div></div>
            <div class="field {{ $err('direction') }}"><label class="f">اتجاه التحسن *</label>
                <select name="direction" id="dir">@foreach (Indicator::DIRECTIONS as $k => $l)<option value="{{ $k }}" @selected($v('direction', 'higher') === $k)>{{ $l }}</option>@endforeach</select></div>
            <div class="field" data-range><label class="f">الحد الأدنى للنطاق</label><input type="number" step="any" name="range_min" value="{{ $v('range_min') }}"></div>
            <div class="field" data-range><label class="f">الحد الأعلى للنطاق</label><input type="number" step="any" name="range_max" value="{{ $v('range_max') }}"></div>
            <div class="field {{ $err('frequency') }}"><label class="f">دورية التحديث *</label>
                <select name="frequency"><option value="">—</option>@foreach (Indicator::FREQUENCIES as $k => $l)<option value="{{ $k }}" @selected($v('frequency') === $k)>{{ $l }}</option>@endforeach</select>
                <div class="hint">السنوي يُقاس في الربع الرابع فقط، والنصف سنوي في الربعين الثاني والرابع.</div></div>
            <div class="field {{ $err('data_source') }}"><label class="f">مصدر البيانات *</label><input type="text" name="data_source" value="{{ $v('data_source') }}"></div>
            <div class="field {{ $err('verification_method') }}"><label class="f">طريقة التحقق *</label><input type="text" name="verification_method" value="{{ $v('verification_method') }}"></div>
            <div class="field full"><label class="f">الأدلة المطلوبة لإثبات الإنجاز</label><input type="text" name="required_evidence" value="{{ $v('required_evidence') }}" placeholder="كشف حضور، تقرير، روابط…"></div>
        </div>
    </div>

    <div class="card">
        <h2>خط الأساس والمستهدفات</h2>
        <div class="form-grid g4">
            <div class="field {{ $err('baseline') }}"><label class="f">خط الأساس *</label><input type="number" step="any" name="baseline" value="{{ $v('baseline') }}"></div>
            <div class="field {{ $err('annual_target') }}"><label class="f">المستهدف السنوي *</label><input type="number" step="any" name="annual_target" id="annual" value="{{ $v('annual_target') }}"></div>
            <div class="field {{ $err('weight') }}"><label class="f">الوزن داخل الهدف % (اختياري)</label><input type="number" step="0.01" min="0" max="100" name="weight" value="{{ $v('weight') }}">
                <div class="hint">اتركه فارغًا لأوزان متساوية؛ إن حددته لمؤشر فحدده لكل مؤشرات الهدف بمجموع 100%.</div></div>
            <div class="field" style="align-self:end"><button type="button" class="btn sec sm" onclick="distribute()">توزيع المستهدف السنوي بالتساوي</button></div>
        </div>
        <div class="table-wrap"><table class="t">
            <thead><tr><th></th>@for ($q = 1; $q <= 4; $q++)<th>الربع {{ ['', 'الأول', 'الثاني', 'الثالث', 'الرابع'][$q] }}</th>@endfor</tr></thead>
            <tbody>
                <tr><th>مستهدف الربع *</th>@for ($q = 1; $q <= 4; $q++)<td><input type="number" step="any" name="targets[{{ $q }}]" value="{{ $tv($q) }}" class="qt" oninput="preview()"></td>@endfor</tr>
                <tr class="sub" id="cum-row"><th>المستهدف التراكمي بنهاية الربع</th>@for ($q = 1; $q <= 4; $q++)<td class="n" id="cum-{{ $q }}">—</td>@endfor</tr>
            </tbody>
        </table></div>
        <p class="hint" id="sum-hint"></p>
    </div>

    <div class="row">
        <button class="btn">حفظ المؤشر</button>
        <a class="btn sec" href="{{ route('plans.edit', $plan) }}">إلغاء</a>
    </div>
</form>
@push('scripts')
<script>
    var hints = {
        cumulative: 'تراكمي: مثل عدد البرامج. يُقارن الفعلي التراكمي بالمستهدف التراكمي بنهاية كل ربع (مثال 2، 3، 3، 4 ← 2، 5، 8، 12).',
        periodic: 'دوري: قيمة لكل ربع مثل المستفيدين في الربع. تُجمع الأرباع فقط إذا اخترت «مجموع الأرباع».',
        point: 'نقطة زمنية: مثل نسبة الرضا. لا تُجمع نسب الأرباع؛ استخدم المتوسط المرجح بعدد المشاركين أو آخر قيمة.'
    };
    var kind = document.getElementById('kind'), agg = document.getElementById('agg'), dir = document.getElementById('dir');
    function num(v) { var n = parseFloat(v); return isNaN(n) ? null : n; }
    function fmt(n) { return (Math.round(n * 100) / 100).toString(); }
    function sync() {
        var k = kind.value;
        document.getElementById('kind-hint').textContent = hints[k];
        Array.prototype.forEach.call(agg.options, function (o) {
            o.disabled = (k === 'cumulative' && o.value !== 'sum') || (k === 'point' && o.value === 'sum') || (k === 'periodic' && o.value === 'weighted_average');
        });
        if (agg.selectedOptions[0].disabled) { agg.value = k === 'point' ? 'weighted_average' : 'sum'; }
        document.querySelectorAll('[data-range]').forEach(function (el) { el.style.display = dir.value === 'range' ? '' : 'none'; });
        preview();
    }
    function preview() {
        var qs = document.querySelectorAll('.qt'), c = 0, sum = 0, all = true;
        var cumulative = kind.value === 'cumulative';
        document.getElementById('cum-row').style.display = cumulative ? '' : 'none';
        qs.forEach(function (i, idx) { var n = num(i.value); if (n === null) all = false; c += n || 0; sum += n || 0; document.getElementById('cum-' + (idx + 1)).textContent = n === null ? '—' : fmt(c); });
        var a = num(document.getElementById('annual').value), h = document.getElementById('sum-hint');
        var sums = cumulative || (kind.value === 'periodic' && agg.value === 'sum');
        h.textContent = sums && all && a !== null ? (Math.abs(sum - a) < 0.01 ? '✓ مجموع مستهدفات الأرباع يساوي المستهدف السنوي.' : '⚠ مجموع مستهدفات الأرباع (' + fmt(sum) + ') لا يساوي المستهدف السنوي (' + fmt(a) + ').') : (kind.value === 'point' ? 'لمؤشر النقطة الزمنية أدخل القيمة المستهدفة في نهاية كل ربع (مثال 80، 80، 80، 80).' : '');
    }
    function distribute() {
        var a = num(document.getElementById('annual').value); if (a === null) return;
        var qs = document.querySelectorAll('.qt');
        var sums = kind.value === 'cumulative' || (kind.value === 'periodic' && agg.value === 'sum');
        if (!sums) { qs.forEach(function (i) { i.value = a; }); return preview(); }
        var base = Math.floor(a / 4 * 100) / 100, acc = 0;
        qs.forEach(function (i, idx) { var v = idx < 3 ? base : Math.round((a - acc) * 100) / 100; acc += v; i.value = v; });
        preview();
    }
    kind.addEventListener('change', sync); agg.addEventListener('change', preview); dir.addEventListener('change', sync);
    document.getElementById('annual').addEventListener('input', preview);
    sync();
</script>
@endpush
@endsection
