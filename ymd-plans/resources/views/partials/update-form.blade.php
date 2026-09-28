{{-- نموذج تحديث الإنجاز: $plan, $openQ (الأرباع المفتوحة), $indicatorId, $taskId, $lockIndicator (اختياري) --}}
@php
    use App\Support\Workspace;
    $inds = $plan->indicators()->get();
    $tasks = $plan->tasks()->where('status', '!=', 'done')->get();
    $mode = $taskId ? 'task' : 'indicator';
    $defQ = $openQ->contains($ctx->quarter) ? $ctx->quarter : $openQ->first();
@endphp
<div class="card" id="new-update">
    <div class="card-head"><h2>إضافة تحديث إنجاز</h2><span class="small muted">يُحفظ بانتظار تحقق مسؤول التخطيط ولا يدخل في الإنجاز الرسمي قبل اعتماده</span></div>
    @if ($openQ->isEmpty())
        <div class="flash info">جميع أرباع السنة مغلقة. لتعديل نتيجة ربع مغلق قدّم طلب تغيير.</div>
    @else
    <form method="post" action="{{ route('updates.store', $plan) }}" enctype="multipart/form-data" id="upd-form">
        @csrf
        @if (empty($lockIndicator))
        <div class="row" style="margin-bottom:.6rem">
            <label class="check"><input type="radio" name="_mode" value="indicator" @checked($mode === 'indicator') onchange="updMode(this.value)"> تحديث مؤشر</label>
            <label class="check"><input type="radio" name="_mode" value="task" @checked($mode === 'task') onchange="updMode(this.value)"> تحديث مهمة</label>
        </div>
        @endif
        <div class="form-grid">
            @if (! empty($lockIndicator))
                <input type="hidden" name="indicator_id" value="{{ $lockIndicator->id }}">
            @else
            <div class="field" data-mode="indicator">
                <label class="f" for="u-ind">المؤشر</label>
                <select id="u-ind" name="indicator_id">
                    <option value="">— اختر —</option>
                    @foreach ($inds as $i)<option value="{{ $i->id }}" @selected((string) $indicatorId === (string) $i->id || old('indicator_id') == $i->id)>{{ $i->name }} ({{ $i->kindLabel() }}{{ $i->unit ? '، ' . $i->unit : '' }})</option>@endforeach
                </select>
            </div>
            <div class="field" data-mode="task">
                <label class="f" for="u-task">المهمة</label>
                <select id="u-task" name="task_id">
                    <option value="">— اختر —</option>
                    @foreach ($tasks as $t)<option value="{{ $t->id }}" @selected((string) $taskId === (string) $t->id || old('task_id') == $t->id)>ر{{ $t->quarter }} — {{ $t->title }}</option>@endforeach
                </select>
            </div>
            @endif
            <div class="field">
                <label class="f" for="u-q">الربع</label>
                <select id="u-q" name="quarter" required>
                    @foreach ($openQ as $n)<option value="{{ $n }}" @selected((int) old('quarter', $defQ) === $n)>{{ Workspace::quarterName($n) }}</option>@endforeach
                </select>
                <div class="hint">الأرباع المغلقة لا تقبل تحديثات مباشرة.</div>
            </div>
            <div class="field">
                <label class="f" for="u-pl">وصف الفترة (اختياري)</label>
                <input id="u-pl" type="text" name="period_label" value="{{ old('period_label') }}" placeholder="مثال: شهر أبريل">
            </div>
            <div class="field" data-mode="indicator">
                <label class="f" for="u-val">القيمة الفعلية للفترة</label>
                <input id="u-val" type="number" step="any" name="actual_value" value="{{ old('actual_value') }}">
                <div class="hint">للمؤشر التراكمي والدوري: ما تحقق خلال هذه الفترة فقط (لا المجموع التراكمي).</div>
            </div>
            <div class="field" data-mode="indicator">
                <label class="f" for="u-part">عدد المشاركين (للنسب والمتوسطات)</label>
                <input id="u-part" type="number" min="0" name="participants" value="{{ old('participants') }}">
                <div class="hint">يُستخدم في المتوسط المرجح لمؤشرات الرضا؛ لا تُجمع النسب.</div>
            </div>
            <div class="field full"><label class="f">ما تم إنجازه</label><textarea name="achieved" rows="2">{{ old('achieved') }}</textarea></div>
            <div class="field"><label class="f">ما لم يتم</label><textarea name="not_achieved" rows="2">{{ old('not_achieved') }}</textarea></div>
            <div class="field"><label class="f">سبب التأخير</label><textarea name="delay_reason" rows="2">{{ old('delay_reason') }}</textarea></div>
            <div class="field"><label class="f">العوائق</label><textarea name="obstacles" rows="2">{{ old('obstacles') }}</textarea></div>
            <div class="field"><label class="f">الدعم المطلوب</label><textarea name="support_needed" rows="2">{{ old('support_needed') }}</textarea></div>
            <div class="field full">
                <label class="f">المرفقات ودليل الإنجاز (حتى 5 ملفات، 10 م.ب لكل ملف)</label>
                <input type="file" name="files[]" multiple accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.csv,.ppt,.pptx,.txt,.zip">
            </div>
            <div class="field full">
                <label class="check"><input type="checkbox" name="claims_completion" value="1" @checked(old('claims_completion'))> أعلن اكتمال هذا العمل (يبقى بانتظار التحقق حتى يعتمده مسؤول التخطيط)</label>
            </div>
        </div>
        <button class="btn">حفظ التحديث وإرساله للتحقق</button>
    </form>
    @endif
</div>
@if (empty($lockIndicator))
@push('scripts')
<script>
    function updMode(m) {
        document.querySelectorAll('#upd-form [data-mode]').forEach(function (el) {
            var on = el.getAttribute('data-mode') === m;
            el.style.display = on ? '' : 'none';
            el.querySelectorAll('select,input').forEach(function (i) { i.disabled = !on; });
        });
    }
    updMode('{{ $mode }}');
</script>
@endpush
@endif
