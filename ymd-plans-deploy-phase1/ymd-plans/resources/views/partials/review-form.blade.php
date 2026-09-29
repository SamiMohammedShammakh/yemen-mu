{{-- اعتماد الدليل أو إعادته مع سبب — مسؤول التخطيط --}}
<form method="post" action="{{ route('updates.review', $update) }}" style="margin-top:.4rem;min-width:200px">
    @csrf
    <textarea name="review_note" rows="2" placeholder="ملاحظة المراجعة (إلزامية عند الإعادة)"></textarea>
    <div class="row" style="margin-top:.3rem">
        <button class="btn sm" name="decision" value="approve">اعتماد الدليل</button>
        <button class="btn danger sm" name="decision" value="return">إعادة مع سبب</button>
    </div>
</form>
