@php use App\Support\Fmt; use App\Support\Workspace; $openQ = $plan->year->quarters->where('status', 'open')->pluck('number'); @endphp
@if ($canUpdate)
    @include('partials.update-form', ['plan' => $plan, 'openQ' => $openQ, 'indicatorId' => request('indicator'), 'taskId' => request('task')])
@elseif (! $plan->acceptsUpdates())
    <div class="flash info">تُضاف التحديثات بعد اعتماد الخطة (معتمدة أو نشطة).</div>
@endif

<div class="card">
    <div class="card-head"><h2>السجل الزمني للتحديثات</h2><span class="small muted">سجل ثابت لا يُحذف ولا يُعدّل من الواجهة</span></div>
    @if ($updates->isEmpty())
        <div class="empty">لا توجد تحديثات بعد.</div>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>#</th><th>المؤشر/المهمة</th><th>الفترة</th><th>القيمة الفعلية</th><th>التفاصيل</th><th>المرفقات</th><th>التحقق</th></tr></thead>
            <tbody>
            @foreach ($updates as $x)
                <tr>
                    <td class="n">#{{ $x->id }}</td>
                    <td>@if ($x->indicator)<a href="{{ route('indicators.show', $x->indicator) }}">{{ $x->indicator->name }}</a>@else {{ $x->task?->title }} <span class="badge outline">مهمة</span>@endif
                        <div class="small muted">{{ $x->creator->name }} · {{ Fmt::dt($x->created_at) }}</div></td>
                    <td class="small">{{ Workspace::quarterName($x->quarter) }}@if ($x->period_label)<br>{{ $x->period_label }}@endif</td>
                    <td class="n">{{ $x->actual_value !== null ? Fmt::num($x->actual_value) : '—' }}@if ($x->participants)<div class="small muted">{{ $x->participants }} مشاركًا</div>@endif</td>
                    <td class="small" style="max-width:340px">
                        @if ($x->achieved)<div><b>ما تم:</b> {{ $x->achieved }}</div>@endif
                        @if ($x->not_achieved)<div><b>ما لم يتم:</b> {{ $x->not_achieved }}</div>@endif
                        @if ($x->delay_reason)<div><b>سبب التأخير:</b> {{ $x->delay_reason }}</div>@endif
                        @if ($x->obstacles)<div><b>العوائق:</b> {{ $x->obstacles }}</div>@endif
                        @if ($x->support_needed)<div><b>الدعم المطلوب:</b> {{ $x->support_needed }}</div>@endif
                        @if ($x->claims_completion)<span class="badge info">يعلن اكتمال العمل</span>@endif
                    </td>
                    <td class="small">@foreach ($x->attachments as $a)<div><a href="{{ route('attachments.show', $a) }}">📎 {{ $a->original_name }}</a></div>@endforeach</td>
                    <td>
                        <span class="badge {{ $x->status }}">{{ $x->statusLabel() }}</span>@if ($x->is_adjustment)<span class="badge warn">تسوية</span>@endif
                        @if ($x->reviewer)<div class="small muted">{{ $x->reviewer->name }} · {{ Fmt::dt($x->reviewed_at) }}</div>@endif
                        @if ($x->review_note)<div class="small">{{ $x->review_note }}</div>@endif
                        @if ($canReview && $x->status === 'pending')
                            @include('partials.review-form', ['update' => $x])
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
        {{ $updates->links('partials.pager') }}
    @endif
</div>
