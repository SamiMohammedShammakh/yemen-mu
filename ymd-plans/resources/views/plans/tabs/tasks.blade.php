@php use App\Support\Fmt; use App\Support\Access; $u = auth()->user(); $canDefer = (Access::isPlanOwner($u, $plan) || Access::canReview($u)) && $plan->acceptsUpdates(); @endphp
<div class="card">
    <div class="card-head">
        <h2>المشاريع والمهام</h2>
        @if ($canEdit)<a class="btn sm" href="{{ route('plans.edit', $plan) }}#projects">إضافة مشروع أو مهمة</a>@endif
    </div>
    @if ($tasks->isEmpty())
        <div class="empty">لا توجد مهام.</div>
    @else
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المهمة</th><th>المشروع</th><th>الربع</th><th>المسؤول</th><th>الموعد</th><th>الدليل المطلوب</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @foreach ($tasks as $t)
            <tr>
                <td><b>{{ $t->title }}</b>@if ($t->description)<div class="small muted">{{ $t->description }}</div>@endif</td>
                <td class="small">@if ($t->project)<a href="{{ route('projects.show', $t->project) }}">{{ $t->project->name }}</a>@else — @endif</td>
                <td>ر{{ $t->quarter }}@if ($t->original_quarter != $t->quarter)<div class="small muted">أصلًا ر{{ $t->original_quarter }}</div>@endif</td>
                <td class="small">{{ $t->responsible ?? $t->owner?->name ?? '—' }}</td>
                <td class="n">{{ Fmt::date($t->due_on) }}</td>
                <td class="small">{{ $t->required_evidence ?? '—' }}</td>
                <td>
                    <span class="badge {{ $t->status }}">{{ $t->statusLabel() }}</span>
                    @if ($t->isOverdue())<span class="badge overdue">متأخرة</span>@endif
                    @foreach ($t->deferrals as $d)
                        <div class="small" style="color:var(--bad)">متأخرة في ر{{ $d->from_quarter }} ← نُقلت إلى ر{{ $d->to_quarter }}: {{ $d->reason }} (الموعد الجديد {{ Fmt::date($d->new_due_on) }})</div>
                    @endforeach
                </td>
                <td>
                    @if ($canUpdate && $t->status !== 'done')
                        <a class="btn sec sm" href="{{ route('plans.show', [$plan, 'tab' => 'updates', 'task' => $t->id]) }}#new-update">تحديث</a>
                    @endif
                    @if ($canDefer && $t->status !== 'done' && $t->quarter < 4)
                        <details><summary class="small" style="cursor:pointer;color:var(--brand-600)">نقل للربع التالي</summary>
                            <form method="post" action="{{ route('tasks.defer', $t) }}" style="margin-top:.4rem;min-width:220px">@csrf
                                <div class="field"><label class="f">سبب النقل</label><textarea name="reason" required rows="2"></textarea></div>
                                <div class="field"><label class="f">الموعد الجديد</label><input type="date" name="new_due_on" required></div>
                                <div class="field"><label class="f">المالك</label>
                                    <select name="owner_user_id">@foreach ($users as $x)<option value="{{ $x->id }}" @selected($x->id === $t->owner_user_id)>{{ $x->name }}</option>@endforeach</select></div>
                                <button class="btn sm">نقل إلى ر{{ $t->quarter + 1 }}</button>
                                <p class="hint">يبقى تأخر المهمة ظاهرًا في الربع {{ $t->quarter }}.</p>
                            </form>
                        </details>
                    @endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div>
    @endif
</div>
