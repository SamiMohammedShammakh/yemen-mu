<div class="form-grid g3">
    <div class="field"><label class="f">النوع</label>
        <select name="type"><option value="project" @selected(($p->type ?? 'project') === 'project')>مشروع</option><option value="initiative" @selected(($p->type ?? '') === 'initiative')>مبادرة</option></select></div>
    <div class="field" style="grid-column: span 2"><label class="f">الاسم</label><input type="text" name="name" value="{{ $p->name ?? '' }}" required></div>
    <div class="field"><label class="f">الهدف المرتبط</label>
        <select name="objective_id"><option value="">—</option>@foreach ($plan->objectives as $o)<option value="{{ $o->id }}" @selected(($p->objective_id ?? null) == $o->id)>{{ $o->title }}</option>@endforeach</select></div>
    <div class="field"><label class="f">المسؤول</label><input type="text" name="responsible" value="{{ $p->responsible ?? auth()->user()->name }}"></div>
    <div class="field"><label class="f">المالك في النظام</label>
        <select name="owner_user_id"><option value="">—</option>@foreach ($users as $x)<option value="{{ $x->id }}" @selected(($p->owner_user_id ?? auth()->id()) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    <div class="field"><label class="f">تاريخ البدء</label><input type="date" name="starts_on" value="{{ $p?->starts_on?->toDateString() }}"></div>
    <div class="field"><label class="f">تاريخ الانتهاء</label><input type="date" name="ends_on" value="{{ $p?->ends_on?->toDateString() }}"></div>
    <div class="field"><label class="f">الموارد</label><input type="text" name="resources" value="{{ $p->resources ?? '' }}"></div>
    <div class="field full"><label class="f">الوصف</label><textarea name="description" rows="2">{{ $p->description ?? '' }}</textarea></div>
</div>
