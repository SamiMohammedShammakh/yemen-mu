@php use App\Support\Fmt; use App\Support\Access; $u = auth()->user(); $isOwner = Access::isPlanOwner($u, $plan); $global = Access::hasGlobalView($u); @endphp
<div class="grid g2">
    <div class="card">
        <h2>ملاحظات المتابعة والردود</h2>
        @if ($global)
            <form method="post" action="{{ route('notes.store', $plan) }}" style="margin-bottom:1rem">@csrf
                <label class="f" for="nb">ملاحظة متابعة جديدة إلى {{ $plan->position->name }}</label>
                <textarea id="nb" name="body" rows="3" required></textarea>
                <button class="btn sm" style="margin-top:.4rem">إرسال الملاحظة</button>
            </form>
        @endif
        @forelse ($notes as $n)
            <div class="note">
                <div class="meta">{{ $n->author->name }} · {{ Fmt::dt($n->created_at) }}</div>
                {!! nl2br(e($n->body)) !!}
            </div>
            @foreach ($n->replies as $r)
                <div class="note reply"><div class="meta">رد: {{ $r->author->name }} · {{ Fmt::dt($r->created_at) }}</div>{!! nl2br(e($r->body)) !!}</div>
            @endforeach
            @if ($isOwner || $global)
                <form method="post" action="{{ route('notes.store', $plan) }}" class="note reply">@csrf
                    <input type="hidden" name="parent_id" value="{{ $n->id }}">
                    <textarea name="body" rows="2" required placeholder="اكتب ردك…"></textarea>
                    <button class="btn sec sm" style="margin-top:.3rem">إرسال الرد</button>
                </form>
            @endif
        @empty
            <p class="muted small">لا توجد ملاحظات متابعة.</p>
        @endforelse
    </div>

    <div class="card">
        <h2>الإجراءات التصحيحية</h2>
        @if ($canReview)
            <details class="box" @if (request('indicator')) open @endif>
                <summary>إنشاء إجراء تصحيحي</summary>
                <div class="in">
                <form method="post" action="{{ route('corrective.store', $plan) }}">@csrf
                    <div class="field"><label class="f">عنوان الإجراء</label><input type="text" name="title" required></div>
                    <div class="field"><label class="f">السبب</label><textarea name="reason" rows="2" required></textarea></div>
                    <div class="form-grid">
                        <div class="field"><label class="f">المالك</label>
                            <select name="owner_user_id" required>@foreach ($users as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
                        <div class="field"><label class="f">الموعد</label><input type="date" name="due_on" required></div>
                        <div class="field"><label class="f">مرتبط بمؤشر (اختياري)</label>
                            <select name="indicator_id"><option value="">—</option>@foreach ($plan->objectives->flatMap->indicators as $i)<option value="{{ $i->id }}" @selected(request('indicator') == $i->id)>{{ $i->name }}</option>@endforeach</select></div>
                        <div class="field"><label class="f">مرتبط بمهمة (اختياري)</label>
                            <select name="task_id"><option value="">—</option>@foreach ($plan->projects->flatMap->tasks as $t)<option value="{{ $t->id }}">{{ $t->title }}</option>@endforeach</select></div>
                    </div>
                    <div class="field"><label class="f">النتيجة المتوقعة</label><textarea name="expected_result" rows="2" required></textarea></div>
                    <button class="btn sm">إنشاء الإجراء وإبلاغ المالك</button>
                </form>
                </div>
            </details>
        @endif
        @forelse ($correctives as $a)
            <div class="note">
                <div class="row between"><b>{{ $a->title }}</b><span><span class="badge {{ $a->status === 'open' ? 'pending' : ($a->status === 'done' ? 'ok' : 'closed') }}">{{ $a->statusLabel() }}</span>@if ($a->isOverdue())<span class="badge overdue">متأخر</span>@endif</span></div>
                <table class="kv small">
                    <tr><th>السبب</th><td>{{ $a->reason }}</td></tr>
                    <tr><th>المالك / الموعد</th><td>{{ $a->owner->name }} · {{ Fmt::date($a->due_on) }}</td></tr>
                    <tr><th>النتيجة المتوقعة</th><td>{{ $a->expected_result }}</td></tr>
                    @if ($a->indicator)<tr><th>المؤشر</th><td>{{ $a->indicator->name }}</td></tr>@endif
                    @if ($a->task)<tr><th>المهمة</th><td>{{ $a->task->title }}</td></tr>@endif
                    @if ($a->result_note)<tr><th>ما تم</th><td>{{ $a->result_note }}</td></tr>@endif
                    <tr><th>أنشأه</th><td>{{ $a->creator->name }} · {{ Fmt::dt($a->created_at) }}</td></tr>
                </table>
                @if ($a->status === 'open' && ($canReview || $isOwner || $a->owner_user_id === $u->id))
                    <form method="post" action="{{ route('corrective.update', $a) }}" class="row" style="margin-top:.3rem">@csrf
                        <input type="text" name="result_note" placeholder="ما تم تنفيذه" style="flex:1;min-width:160px">
                        <button class="btn sm" name="status" value="done">تم التنفيذ</button>
                        @if ($canReview)<button class="btn danger sm" name="status" value="cancelled">إلغاء</button>@endif
                    </form>
                @endif
            </div>
        @empty
            <p class="muted small">لا توجد إجراءات تصحيحية.</p>
        @endforelse
    </div>
</div>
