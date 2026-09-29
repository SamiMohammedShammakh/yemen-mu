@php use App\Support\Fmt; use App\Support\Workspace; @endphp
<div class="grid g2">
    <div class="card">
        <h2>النسخ المعتمدة</h2>
        @forelse ($versions as $v)
            <div class="row between" style="padding:.4rem 0;border-bottom:1px solid var(--line-2)">
                <span><a href="{{ route('plans.version', [$plan, $v->version_no]) }}"><b>v{{ $v->version_no }}</b></a> — {{ $v->reason }}
                    <div class="small muted">اعتمدها {{ $v->approver?->name }} · سارية من {{ Fmt::dt($v->effective_from) }} {{ $v->effective_to ? 'حتى ' . Fmt::dt($v->effective_to) : '(النسخة السارية)' }}</div></span>
                @if (! $v->effective_to)<span class="badge ok">سارية</span>@endif
            </div>
        @empty
            <p class="muted small">لم تُعتمد أي نسخة بعد.</p>
        @endforelse
    </div>
    <div class="card">
        <h2>لقطات إقفال الأرباع</h2>
        @forelse ($snapshots as $s)
            <div style="padding:.3rem 0;border-bottom:1px solid var(--line-2)">
                <b>{{ Workspace::quarterName($s->quarter) }}</b> — مراجعة {{ $s->revision }} · نسخة الخطة v{{ $s->plan_version_no }} · قواعد الحالات نسخة {{ $s->rules['version'] ?? '—' }}
                <div class="small muted">{{ $s->creator->name }} · {{ Fmt::dt($s->created_at) }}@if ($s->change_request_id) · بطلب التغيير #{{ $s->change_request_id }}@endif</div>
            </div>
        @empty
            <p class="muted small">لم يُقفل أي ربع بعد.</p>
        @endforelse
    </div>
</div>

<div class="card">
    <h2>سجل دورة الاعتماد</h2>
    <ul class="timeline">
        @forelse ($reviews as $r)
            <li><b>{{ $r->actionLabel() }}</b> — {{ $r->user->name }} <span class="small muted">({{ \App\Models\Plan::STATUSES[$r->from_status] ?? $r->from_status }} ← {{ \App\Models\Plan::STATUSES[$r->to_status] ?? $r->to_status }}{{ $r->version_no ? '، v' . $r->version_no : '' }})</span>
                @if ($r->delegation)<span class="badge warn">بتفويض: {{ $r->delegation->document_ref }}</span>@endif
                @if ($r->note)<div class="small">{{ $r->note }}</div>@endif
                <div class="when">{{ Fmt::dt($r->created_at) }}</div></li>
        @empty
            <li class="muted">لا توجد إجراءات بعد.</li>
        @endforelse
    </ul>
</div>

<div class="card">
    <h2>طلبات التعديل</h2>
    @forelse ($crs as $cr)
        <div class="small" style="padding:.25rem 0"><a href="{{ route('change-requests.show', $cr) }}">#{{ $cr->id }} {{ $cr->typeLabel() }}</a> — <span class="badge {{ $cr->status }}">{{ $cr->statusLabel() }}</span> {{ $cr->requester->name }} · {{ Fmt::dt($cr->created_at) }}</div>
    @empty
        <p class="muted small">لا توجد طلبات تعديل.</p>
    @endforelse
</div>
