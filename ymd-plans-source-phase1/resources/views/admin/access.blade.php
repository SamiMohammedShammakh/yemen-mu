@extends('layouts.app')
@section('title', 'الإدارة التنفيذية والتفويض')
@section('no_context', true)
@php use App\Support\Fmt; use App\Support\Access; $me = auth()->user(); @endphp
@section('content')
<div class="ws-title"><h1>مجموعة الإدارة التنفيذية وتفويض الاعتماد</h1></div>

<div class="grid g2">
    <div class="card">
        <h2>الصلاحيات حسب المنصب</h2>
        <div class="table-wrap"><table class="t">
            <thead><tr><th>المنصب</th><th>رؤية جميع الخطط بحكم المنصب</th><th>ملاحظة</th></tr></thead>
            <tbody>
            @foreach ($positions as $p)
                <tr><td>{{ $p->name }}</td>
                    <td>@if ($p->global_view)<span class="badge ok">نعم</span>@else<span class="badge">لا — خطته فقط</span>@endif</td>
                    <td class="small">@if ($p->is_president)اعتماد الخطط وطلبات التعديل@elseif ($p->is_planning)مراجعة الخطط والتحقق من الأدلة وإقفال الأرباع@elseif ($p->code === 'vice_president')رؤية شاملة فقط بعضوية تنفيذية صريحة@endif</td></tr>
            @endforeach
            </tbody>
        </table></div>
    </div>

    <div class="card">
        <h2>إضافة عضو إلى الإدارة التنفيذية</h2>
        <p class="small muted">لا تُمنح العضوية تلقائيًا بسبب المنصب. العضو المعتمد يرى جميع الخطط والنتائج ويفتح لوحة الرئيس والإدارة التنفيذية. يُسجَّل من منح ومتى ولماذا.</p>
        <form method="post" action="{{ route('admin.executive.grant') }}">@csrf
            <div class="field"><label class="f">المستخدم</label>
                <select name="user_id" required>@foreach ($users as $x)<option value="{{ $x->id }}">{{ $x->name }} — {{ Access::positions($x)->pluck('name')->implode('، ') ?: 'بلا منصب' }}</option>@endforeach</select></div>
            <div class="field"><label class="f">سند / سبب المنح (قرار، محضر…)</label><textarea name="grant_reason" rows="2" required></textarea></div>
            <button class="btn">منح العضوية</button>
        </form>
    </div>
</div>

<div class="card">
    <h2>سجل عضوية الإدارة التنفيذية</h2>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>العضو</th><th>منحها</th><th>تاريخ المنح وسببه</th><th>الحالة</th><th>أوقفها</th><th></th></tr></thead>
        <tbody>
        @forelse ($memberships as $m)
            <tr>
                <td><b>{{ $m->user->name }}</b></td>
                <td class="small">{{ $m->granter->name }}</td>
                <td class="small">{{ Fmt::dt($m->granted_at) }}<br>{{ $m->grant_reason }}</td>
                <td>@if ($m->revoked_at)<span class="badge closed">موقوفة</span>@else<span class="badge ok">سارية</span>@endif</td>
                <td class="small">@if ($m->revoked_at){{ $m->revoker?->name }} · {{ Fmt::dt($m->revoked_at) }}<br>{{ $m->revoke_reason }}@endif</td>
                <td>@unless ($m->revoked_at)
                    <form method="post" action="{{ route('admin.executive.revoke', $m) }}" class="dl-form">@csrf
                        <input type="text" name="revoke_reason" placeholder="سبب الإيقاف" required style="width:150px">
                        <button class="btn danger sm">إيقاف</button>
                    </form>@endunless</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">لا توجد عضويات. لا أحد يرى جميع الخطط غير الرئيس ومسؤول التخطيط.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

<div class="card">
    <h2>تفويض اعتماد الخطط</h2>
    <p class="small muted">اعتماد الخطط وطلبات التعديل للرئيس، أو لمفوّض بوثيقة موثقة. المفوّض يرى فقط الخطط التي تنتظر قراره، ويُسجَّل رقم الوثيقة مع كل اعتماد.</p>
    @if (Access::isPresident($me) || $me->is_system_admin)
        <form method="post" action="{{ route('admin.delegations.store') }}" class="form-grid g4" style="margin-bottom:1rem">@csrf
            <div class="field"><label class="f">المفوَّض</label><select name="user_id" required>@foreach ($users as $x)<option value="{{ $x->id }}">{{ $x->name }}</option>@endforeach</select></div>
            <div class="field"><label class="f">رقم/مرجع وثيقة التفويض</label><input type="text" name="document_ref" required></div>
            <div class="field"><label class="f">من</label><input type="date" name="starts_on" value="{{ now()->toDateString() }}" required></div>
            <div class="field"><label class="f">إلى (اختياري)</label><input type="date" name="ends_on"></div>
            <div class="full"><button class="btn sm">تسجيل التفويض</button></div>
        </form>
    @endif
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المفوَّض</th><th>الوثيقة</th><th>المدة</th><th>منحه</th><th>الحالة</th><th></th></tr></thead>
        <tbody>
        @forelse ($delegations as $d)
            <tr>
                <td>{{ $d->user->name }}</td><td>{{ $d->document_ref }}</td>
                <td class="n">{{ Fmt::date($d->starts_on) }} ← {{ $d->ends_on ? Fmt::date($d->ends_on) : 'مفتوح' }}</td>
                <td class="small">{{ $d->granter->name }} · {{ Fmt::dt($d->created_at) }}</td>
                <td>@if ($d->revoked_at)<span class="badge closed">ملغى {{ Fmt::date($d->revoked_at) }}</span>@elseif ($d->ends_on && $d->ends_on->lt(now()->startOfDay()))<span class="badge closed">منتهٍ</span>@else<span class="badge ok">ساري</span>@endif</td>
                <td>@if (! $d->revoked_at && (Access::isPresident($me) || $me->is_system_admin))<form method="post" action="{{ route('admin.delegations.revoke', $d) }}" class="inline">@csrf<button class="btn danger sm" data-confirm="إلغاء التفويض؟">إلغاء</button></form>@endif</td>
            </tr>
        @empty
            <tr><td colspan="6" class="muted">لا توجد تفويضات.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>
@endsection
