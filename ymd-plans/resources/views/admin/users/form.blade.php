@extends('layouts.app')
@section('title', $user ? 'إدارة حساب ' . $user->name : 'حساب جديد')
@section('no_context', true)
@php use App\Support\Fmt; $me = auth()->user(); @endphp
@section('content')
<div class="ws-title"><h1>{{ $user ? 'إدارة حساب: ' . $user->name : 'حساب جديد' }}</h1><a class="btn sec right" href="{{ route('admin.users.index') }}">← الحسابات</a></div>
<div class="grid g2">
    <div class="card">
        <h2>بيانات الحساب</h2>
        <form method="post" action="{{ $user ? route('admin.users.update', $user) : route('admin.users.store') }}">@csrf @if ($user) @method('put') @endif
            <div class="field"><label class="f">الاسم</label><input type="text" name="name" value="{{ old('name', $user?->name) }}" required></div>
            <div class="field"><label class="f">البريد الإلكتروني (اسم الدخول)</label><input type="email" name="email" value="{{ old('email', $user?->email) }}" required dir="ltr"></div>
            <div class="field"><label class="f">الهاتف</label><input type="text" name="phone" value="{{ old('phone', $user?->phone) }}" dir="ltr"></div>
            <div class="field"><label class="f">{{ $user ? 'كلمة مرور جديدة (اتركها فارغة لعدم التغيير)' : 'كلمة المرور' }}</label><input type="password" name="password" autocomplete="new-password" {{ $user ? '' : 'required' }} dir="ltr"><div class="hint">8 أحرف على الأقل، تحتوي حروفًا وأرقامًا.</div></div>
            @if ($user)
                <label class="check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active))> الحساب فعّال</label>
            @else
                <div class="form-grid">
                    <div class="field"><label class="f">المنصب</label><select name="position_id"><option value="">—</option>@foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                    <div class="field"><label class="f">بداية الإسناد</label><input type="date" name="starts_on" value="{{ now()->toDateString() }}"></div>
                </div>
            @endif
            @if ($me->is_system_admin)
                <label class="check"><input type="checkbox" name="is_system_admin" value="1" @checked(old('is_system_admin', $user?->is_system_admin))> مدير النظام التقني (إدارة الحسابات فقط — لا يمنح قراءة الخطط)</label>
            @endif
            <button class="btn" style="margin-top:.8rem">حفظ</button>
        </form>
    </div>

    @if ($user)
    <div class="card">
        <h2>المناصب</h2>
        <p class="small muted">يمكن أن يشغل المستخدم أكثر من منصب، ويبدّل بينها من أعلى الصفحة. إنهاء الإسناد يحفظ السجل ولا يحذفه.</p>
        <div class="table-wrap" style="margin-bottom:.8rem"><table class="t">
            <thead><tr><th>المنصب</th><th>من</th><th>إلى</th><th>أسنده</th><th></th></tr></thead>
            <tbody>
            @forelse ($user->positionAssignments as $a)
                <tr>
                    <td>{{ $a->position->name }} @if ($a->is_primary)<span class="badge outline">أساسي</span>@endif @if ($a->isCurrent())<span class="badge ok">حالي</span>@endif</td>
                    <td class="n">{{ Fmt::date($a->starts_on) }}</td>
                    <td class="n">{{ Fmt::date($a->ends_on) }}</td>
                    <td class="small">{{ $a->assigner?->name }}</td>
                    <td>@if ($a->isCurrent())
                        <form method="post" action="{{ route('admin.assignments.end', $a) }}" class="dl-form">@csrf
                            <input type="date" name="ends_on" value="{{ now()->toDateString() }}" required style="width:auto">
                            <button class="btn danger sm">إنهاء</button>
                        </form>@endif</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">لا توجد مناصب.</td></tr>
            @endforelse
            </tbody>
        </table></div>
        <form method="post" action="{{ route('admin.users.assign', $user) }}">@csrf
            <div class="form-grid">
                <div class="field"><label class="f">إسناد منصب</label><select name="position_id" required>@foreach ($positions as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select></div>
                <div class="field"><label class="f">من تاريخ</label><input type="date" name="starts_on" value="{{ now()->toDateString() }}" required></div>
            </div>
            <label class="check"><input type="checkbox" name="is_primary" value="1"> المنصب الأساسي (تُفتح لوحته عند الدخول)</label>
            <button class="btn sm" style="margin-top:.5rem">إسناد</button>
        </form>
    </div>
    @endif
</div>
@endsection
