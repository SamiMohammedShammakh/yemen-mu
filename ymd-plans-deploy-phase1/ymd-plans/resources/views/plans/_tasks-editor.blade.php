{{-- مهام مبادرة أو نشاط: $holder (Project) --}}
@php use App\Support\Fmt; @endphp
@foreach ($holder->tasks as $t)
    <details class="box"><summary class="small"><span class="num muted">{{ $t->ref }}</span> ر{{ $t->quarter }} — {{ $t->title }} <span class="muted">{{ Fmt::date($t->due_on) }}{{ $t->owner ? ' · ' . $t->owner->name : '' }}</span></summary>
        <div class="in">
            <form method="post" action="{{ route('tasks.update', $t) }}">@csrf @method('put')
                @include('plans._task-fields', ['t' => $t, 'projectId' => $holder->id])
                <button class="btn sec sm">حفظ المهمة</button>
            </form>
            <form method="post" action="{{ route('tasks.destroy', $t) }}" style="margin-top:.4rem">@csrf @method('delete')<button class="btn danger sm" data-confirm="حذف المهمة {{ $t->ref }}؟">حذف</button></form>
        </div>
    </details>
@endforeach
<form method="post" action="{{ route('tasks.store', $plan) }}" class="card" style="background:var(--bg);box-shadow:none;margin-top:.5rem">@csrf
    <h3>+ مهمة جديدة ضمن {{ $holder->ref }}</h3>
    @include('plans._task-fields', ['t' => null, 'projectId' => $holder->id])
    <button class="btn sm">إضافة المهمة</button>
</form>
