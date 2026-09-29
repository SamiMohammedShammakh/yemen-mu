@extends('layouts.app')
@section('title', 'سجل التدقيق')
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title"><h1>سجل التدقيق</h1></div>
<form method="get" class="row" style="margin-bottom:1rem">
    <select name="action" style="max-width:260px" onchange="this.form.submit()">
        <option value="">كل الإجراءات</option>
        @foreach (['plan.' => 'الخطط ودورة الاعتماد', 'update.' => 'التحديثات والتحقق', 'change_request.' => 'طلبات التعديل', 'export.' => 'إنشاء الملفات وتنزيلها', 'executive.' => 'عضوية الإدارة التنفيذية', 'delegation.' => 'التفويض', 'assignment.' => 'إسناد المناصب', 'user.' => 'الحسابات', 'quarter.' => 'إقفال الأرباع', 'auth.' => 'الدخول والخروج'] as $k => $l)
            <option value="{{ $k }}" @selected(request('action') === $k)>{{ $l }}</option>
        @endforeach
    </select>
</form>
<div class="table-wrap"><table class="t">
    <thead><tr><th>التاريخ</th><th>المستخدم</th><th>الإجراء</th><th>العنصر</th><th>القيم السابقة</th><th>القيم الجديدة</th><th>IP</th></tr></thead>
    <tbody>
    @foreach ($logs as $l)
        <tr>
            <td class="n">{{ Fmt::dt($l->created_at) }}</td>
            <td class="small">{{ $l->user?->name ?? 'النظام' }}</td>
            <td><code>{{ $l->action }}</code></td>
            <td class="small">{{ $l->entity_type }} #{{ $l->entity_id }}{{ $l->plan_id ? ' · خطة ' . $l->plan_id : '' }}</td>
            <td class="small" style="max-width:260px;word-break:break-word">{{ $l->old_values ? json_encode($l->old_values, JSON_UNESCAPED_UNICODE) : '' }}</td>
            <td class="small" style="max-width:320px;word-break:break-word">{{ $l->new_values ? \Illuminate\Support\Str::limit(json_encode($l->new_values, JSON_UNESCAPED_UNICODE), 300) : '' }}</td>
            <td class="n small">{{ $l->ip }}</td>
        </tr>
    @endforeach
    </tbody>
</table></div>
{{ $logs->links('partials.pager') }}
@endsection
