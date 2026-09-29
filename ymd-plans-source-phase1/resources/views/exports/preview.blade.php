@extends('layouts.app')
@section('title', 'معاينة الملف قبل الإنشاء')
@php use App\Support\Fmt; $h = $p['header']; @endphp
@section('content')
<div class="ws-title"><h1>معاينة الملف قبل الإنشاء</h1></div>
<div class="grid g-2-1">
    <div class="card">
        <h2>{{ $h['title'] }}</h2>
        <table class="kv">
            <tr><th>الجهة</th><td>{{ $h['org'] }}</td></tr>
            <tr><th>المنصب</th><td><b>{{ $h['position'] }}</b></td></tr>
            <tr><th>السنة</th><td>{{ $h['year'] }}</td></tr>
            <tr><th>الربع</th><td>{{ $h['quarter'] }}</td></tr>
            <tr><th>رقم نسخة الخطة</th><td>{{ $h['version'] }}</td></tr>
            <tr><th>تاريخ البيانات</th><td class="num">{{ $h['data_as_of'] }}</td></tr>
            <tr><th>حالة الاعتماد</th><td>
                @if ($h['status'] === 'معتمدة')<span class="badge ok">معتمدة</span>@elseif ($h['status'] === 'مسودة')<span class="badge warn">مسودة غير معتمدة</span>@else<span class="badge info">{{ $h['status'] }}</span>@endif
                @if ($h['status_detail'])<span class="small muted">({{ $h['status_detail'] }})</span>@endif</td></tr>
            @if ($p['source'])<tr><th>مصدر الأرقام</th><td>{{ $p['source'] === 'snapshot' ? 'لقطة إقفال الربع (ثابتة)' : 'حساب مباشر من التحديثات المعتمدة حتى الآن' }}</td></tr>@endif
            <tr><th>قواعد الحالات</th><td>نسخة {{ $p['rules']['version'] }}: ≥{{ Fmt::num($p['rules']['on_track_min']) }}% يسير حسب الخطة، ≥{{ Fmt::num($p['rules']['follow_up_min']) }}% يحتاج متابعة</td></tr>
            <tr><th>الصيغة</th><td>{{ strtoupper($p['format']) }}</td></tr>
            <tr><th>اسم الملف</th><td dir="ltr" style="text-align:right"><code>{{ $p['file_name'] }}</code></td></tr>
        </table>
        @if ($h['status'] === 'مسودة')
            <div class="flash warn small" style="margin-top:.8rem">سيحمل الملف علامة «مسودة» في رأسه وصفحاته، ولا تُعد أرقامه رسمية.</div>
        @endif
        <form method="post" action="{{ route('exports.store') }}" style="margin-top:1rem">@csrf
            @foreach ($in as $k => $v)@if ($v !== null && $v !== '')<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
            <button class="btn">إنشاء الملف</button>
            <a class="btn sec" href="{{ url()->previous() }}">رجوع</a>
        </form>
    </div>
    <div class="card">
        <h2>ملاحظات</h2>
        <ul class="small">
            <li>يُعاد التحقق من صلاحيتك عند إنشاء الملف وعند كل تنزيل، ويُسجَّل من أنشأه ومتى.</li>
            <li>الأرقام في الملف هي نفس الأرقام المعروضة في النظام للسنة والربع المختارين.</li>
            <li>الملف نسخة مستقلة؛ تعديله بعد التنزيل لا يغيّر الخطة داخل النظام.</li>
            <li>لا تدخل التحديثات بانتظار التحقق في الإنجاز الرسمي.</li>
        </ul>
    </div>
</div>
@endsection
