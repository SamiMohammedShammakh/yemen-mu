{{-- حقول النشاط: $a (Project|null), $parent (المبادرة الأم) --}}
<input type="hidden" name="type" value="activity">
<input type="hidden" name="parent_id" value="{{ $parent->id }}">
<div class="form-grid g3">
    <div class="field" style="grid-column: span 2"><label class="f">اسم النشاط</label><input type="text" name="name" value="{{ $a->name ?? '' }}" required></div>
    <div class="field"><label class="f">المسؤول عن النشاط</label>
        <select name="owner_user_id"><option value="">—</option>@foreach ($users as $x)<option value="{{ $x->id }}" @selected(($a->owner_user_id ?? auth()->id()) == $x->id)>{{ $x->name }}</option>@endforeach</select></div>
    <div class="field"><label class="f">الجهة/الشخص المنفذ (نص)</label><input type="text" name="responsible" value="{{ $a->responsible ?? '' }}"></div>
    <div class="field"><label class="f">تاريخ البدء</label><input type="date" name="starts_on" value="{{ $a?->starts_on?->toDateString() }}"></div>
    <div class="field"><label class="f">تاريخ الانتهاء</label><input type="date" name="ends_on" value="{{ $a?->ends_on?->toDateString() }}"></div>
    <div class="field full"><label class="f">الوصف والمخرج المتوقع</label><textarea name="description" rows="2">{{ $a->description ?? '' }}</textarea></div>
</div>
