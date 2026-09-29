<input type="hidden" name="project_id" value="{{ $projectId }}">
<div class="form-grid g3">
    <div class="field" style="grid-column: span 2"><label class="f">عنوان المهمة</label><input type="text" name="title" value="{{ $t->title ?? '' }}" required></div>
    <div class="field"><label class="f">الربع</label>
        <select name="quarter">@for ($k = 1; $k <= 4; $k++)<option value="{{ $k }}" @selected(($t->quarter ?? 1) == $k)>{{ \App\Support\Workspace::quarterName($k) }}</option>@endfor</select></div>
    <div class="field"><label class="f">الجهة/الشخص المنفذ (نص)</label><input type="text" name="responsible" value="{{ $t->responsible ?? auth()->user()->name }}"></div>
    <div class="field"><label class="f">المسؤول (حساب في النظام)</label>
        <select name="owner_user_id"><option value="">—</option>@foreach ($users as $x)<option value="{{ $x->id }}" @selected(($t->owner_user_id ?? auth()->id()) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    <div class="field"><label class="f">الموعد</label><input type="date" name="due_on" value="{{ $t?->due_on?->toDateString() }}"></div>
    <div class="field full"><label class="f">الدليل المطلوب لإثبات الإنجاز</label><input type="text" name="required_evidence" value="{{ $t->required_evidence ?? '' }}"></div>
</div>
