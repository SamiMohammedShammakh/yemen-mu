@extends('layouts.app')
@section('title', 'الحسابات والمناصب')
@section('no_context', true)
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title"><h1>الحسابات والمناصب</h1><a class="btn right" href="{{ route('admin.users.create') }}">+ حساب جديد</a></div>
<div class="flash info small">إدارة الحسابات لا تمنح صلاحية قراءة الخطط. الرؤية الشاملة للرئيس ومسؤول التخطيط والمتابعة بحكم المنصب، ولأعضاء الإدارة التنفيذية المعتمدين فقط (من <a href="{{ route('admin.access.index') }}">الإدارة التنفيذية والتفويض</a>). نائب الرئيس لا يرى خطط الآخرين إلا بعضوية تنفيذية صريحة.</div>
<div class="table-wrap"><table class="t">
    <thead><tr><th>الاسم</th><th>البريد</th><th>المناصب الحالية</th><th>صلاحيات خاصة</th><th>الحالة</th><th>آخر دخول</th><th></th></tr></thead>
    <tbody>
    @foreach ($users as $x)
        <tr>
            <td><b>{{ $x->name }}</b></td>
            <td class="small" dir="ltr" style="text-align:right">{{ $x->email }}</td>
            <td class="small">
                @foreach ($x->positionAssignments->filter->isCurrent() as $a)<span class="badge outline">{{ $a->position->name }}{{ $a->is_primary ? ' (أساسي)' : '' }}</span> @endforeach
                @if ($x->positionAssignments->filter->isCurrent()->isEmpty())<span class="muted">—</span>@endif
            </td>
            <td class="small">
                @if ($x->is_system_admin)<span class="badge">مدير النظام التقني</span>@endif
                @if ($x->executiveMemberships->isNotEmpty())<span class="badge info">الإدارة التنفيذية</span>@endif
            </td>
            <td>@if ($x->is_active)<span class="badge ok">فعّال</span>@else<span class="badge late">موقوف</span>@endif</td>
            <td class="n">{{ Fmt::dt($x->last_login_at) }}</td>
            <td><a class="btn sec sm" href="{{ route('admin.users.edit', $x) }}">إدارة</a></td>
        </tr>
    @endforeach
    </tbody>
</table></div>
@endsection
