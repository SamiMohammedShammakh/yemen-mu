@php use App\Support\Fmt; use App\Support\Workspace; @endphp
<div class="grid g-2-1">
    <div class="card">
        <h2>بيانات الخطة</h2>
        <table class="kv">
            <tr><th>الرقم المرجعي</th><td class="num"><b>{{ $plan->ref }}</b></td></tr>
            <tr><th>السنة</th><td>{{ $plan->year->displayName() }} ({{ $plan->year->year }})</td></tr>
            <tr><th>المنصب</th><td>{{ $plan->position->name }}</td></tr>
            <tr><th>مالك الخطة</th><td>{{ $plan->owner->name }}</td></tr>
            <tr><th>وصف نطاق العمل</th><td>{!! nl2br(e($plan->scope_description ?: '—')) !!}</td></tr>
            <tr><th>النتيجة العامة</th><td>{!! nl2br(e($plan->overall_outcome ?: '—')) !!}</td></tr>
            <tr><th>المخاطر</th><td>{!! nl2br(e($plan->risks ?: '—')) !!}</td></tr>
            <tr><th>الموارد</th><td>{!! nl2br(e($plan->resources ?: '—')) !!}</td></tr>
        </table>
    </div>
    <div class="card">
        <h2>الأرباع الأربعة</h2>
        <div class="stack">
            @foreach ($plan->year->quarters as $qq)
                <a class="qcard {{ $qq->number === $q ? 'on' : '' }}" href="{{ route('plans.quarter', [$plan, $qq->number]) }}">
                    <div class="h"><span>{{ $qq->label() }}</span>@if ($qq->isClosed())<span class="badge closed">مغلق — لقطة محفوظة</span>@else<span class="badge outline">مفتوح</span>@endif</div>
                    <div class="d"><span class="num">{{ Fmt::date($qq->starts_on) }}</span> – <span class="num">{{ Fmt::date($qq->ends_on) }}</span></div>
                </a>
            @endforeach
        </div>
    </div>
</div>

@if ($plan->objectives->isNotEmpty())
    <div class="card">
        <h2>أهداف الخطة وربطها بالأهداف الاستراتيجية</h2>
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الرقم</th><th>هدف الخطة</th><th>الوزن</th><th>الهدف الاستراتيجي للجمعية</th></tr></thead>
            <tbody>
            @foreach ($plan->objectives as $o)
                <tr>
                    <td class="n">{{ $o->ref }}</td>
                    <td><a href="{{ route('objectives.show', $o) }}">{{ $o->title }}</a></td>
                    <td class="n">{{ Fmt::num($o->weight) }}%</td>
                    <td>@if ($o->strategicGoal)<a href="{{ route('strategic-goals.show', $o->strategicGoal) }}">{{ $o->strategicGoal->label() }}</a>@else<span class="muted small">غير مرتبط</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endif

@if ($results)
    <div class="card">
        <div class="card-head">
            <h2>نتائج الخطة — {{ Workspace::quarterName($q) }}</h2>
            <div class="row">
                @include('partials.download', ['scope' => 'results_quarter', 'plan' => $plan, 'quarter' => $q, 'label' => 'نتائج الربع'])
                @include('partials.download', ['scope' => 'results_annual', 'plan' => $plan, 'quarter' => $q, 'label' => 'النتائج السنوية'])
            </div>
        </div>
        @if (! $plan->isOfficial())
            <div class="flash warn small">الخطة غير معتمدة بعد؛ الأرقام أدناه غير رسمية.</div>
        @endif
        <div class="grid g4">
            @include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف المرحلي', 'v' => $results['period_achievement'], 'key' => $results['status']['key']])
            @include('partials.ach', ['label' => 'إنجاز الخطة مقابل المستهدف السنوي', 'v' => $results['annual_achievement'], 'key' => 'on_track'])
            <div class="ach"><span class="lbl">الحالة</span>@include('partials.status', ['s' => $results['status']])</div>
            <div class="ach"><span class="lbl">مصدر الأرقام</span><span class="small">{{ $results['source'] === 'snapshot' ? 'لقطة إقفال الربع (مراجعة ' . ($results['snapshot_revision'] ?? 1) . ')' : 'حساب مباشر من التحديثات المعتمدة' }}</span></div>
        </div>
        <details style="margin-top:.6rem"><summary class="small muted" style="cursor:pointer">كيف يُحسب إنجاز الخطة؟</summary>
            <div class="explain" style="margin-top:.4rem">
                إنجاز المؤشر = الفعلي المعتمد مقارنةً بالمستهدف المرحلي المناسب لنوعه (تراكمي: المستهدف التراكمي حتى نهاية الربع). إنجاز الهدف = متوسط مرجح بأوزان مؤشراته المقاسة، وإنجاز الخطة = متوسط مرجح بأوزان أهدافها المقاسة. تُقصّ نسبة كل مؤشر عند 100% قبل التجميع.
                حدود الحالات (نسخة {{ $results['rules']['version'] }}): يسير حسب الخطة ≥ {{ Fmt::num($results['rules']['on_track_min']) }}%، يحتاج متابعة ≥ {{ Fmt::num($results['rules']['follow_up_min']) }}%، وما دون ذلك متأخر.
                تغطية القياس الحالية {{ Fmt::pct($results['coverage']) }} من أوزان الأهداف.
            </div>
        </details>
    </div>
    @include('partials.results-table', ['results' => $results, 'downloadPlan' => $plan])
@else
    <div class="empty">لم تُضف أهداف لهذه الخطة بعد.</div>
@endif

@if ($plan->projects->isNotEmpty())
    <div class="card">
        <h2>المبادرات والمشاريع</h2>
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الاسم</th><th>النوع</th><th>المسؤول</th><th>المدة</th><th>المهام</th><th></th></tr></thead>
            <tbody>
            @foreach ($plan->projects->whereNull('parent_id') as $p)
                <tr>
                    <td><span class="num small muted">{{ $p->ref }}</span> <a href="{{ route('projects.show', $p) }}">{{ $p->name }}</a>@if ($p->activities->isNotEmpty())<div class="small muted">{{ $p->activities->count() }} نشاط</div>@endif</td>
                    <td>{{ $p->typeLabel() }}</td>
                    <td>{{ $p->responsible ?? '—' }}</td>
                    <td class="n"><span class="num">{{ Fmt::date($p->starts_on) }}</span> – <span class="num">{{ Fmt::date($p->ends_on) }}</span></td>
                    <td class="n">{{ $p->tasks->count() }}</td>
                    <td>@include('partials.download', ['scope' => 'project', 'plan' => $plan, 'subject' => $p->id, 'label' => 'المشروع'])</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    </div>
@endif
