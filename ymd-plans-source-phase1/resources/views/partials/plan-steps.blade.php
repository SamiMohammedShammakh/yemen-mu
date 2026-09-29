@php
    $flow = ['draft' => 'مسودة', 'submitted' => 'مرسلة لمراجعة التخطيط', 'recommended' => 'موصى باعتمادها', 'approved' => 'معتمدة من الرئيس', 'active' => 'نشطة', 'closed' => 'مغلقة'];
    $order = array_keys($flow);
    $cur = $plan->status === 'returned' ? 'draft' : $plan->status;
    $idx = array_search($cur, $order, true);
@endphp
<div class="steps" aria-label="مراحل اعتماد الخطة">
    @foreach ($flow as $k => $label)
        @php $i = array_search($k, $order, true); @endphp
        <span class="{{ $i < $idx ? 'done' : ($i === $idx ? 'cur' : '') }}">
            @if ($k === 'draft' && $plan->status === 'returned') معادة للتعديل @else {{ $label }} @endif
        </span>
        @if (! $loop->last)<i>←</i>@endif
    @endforeach
</div>
