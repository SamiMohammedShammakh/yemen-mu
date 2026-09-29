@php use App\Support\Fmt; use App\Support\Workspace; $hasRes = $plan->objectives->isNotEmpty(); @endphp
<div class="card">
    <h2>تنزيل الخطة ونتائجها</h2>
    <p class="small muted">تُعرض معاينة قبل إنشاء الملف (المنصب، السنة، الربع، رقم النسخة، تاريخ البيانات، حالة الاعتماد). الملف نسخة مستقلة: تعديله بعد التنزيل لا يغيّر الخطة في النظام.</p>
    <div class="table-wrap"><table class="t">
        <thead><tr><th>المحتوى</th><th>الصيغ</th><th></th></tr></thead>
        <tbody>
            <tr><td>الخطة كاملة</td><td class="small">PDF · DOCX · XLSX · CSV</td><td>@include('partials.download', ['scope' => 'plan_full', 'plan' => $plan])</td></tr>
            @for ($k = 1; $k <= 4; $k++)
                <tr><td>خطة {{ Workspace::quarterName($k) }}</td><td class="small">PDF · DOCX · XLSX · CSV</td><td>@include('partials.download', ['scope' => 'plan_quarter', 'plan' => $plan, 'quarter' => $k])</td></tr>
            @endfor
            @if ($hasRes)
                <tr><td>نتائج الخطة السنوية (حتى {{ Workspace::quarterName($q) }})</td><td class="small">PDF · DOCX · XLSX · CSV</td><td>@include('partials.download', ['scope' => 'results_annual', 'plan' => $plan, 'quarter' => $q])</td></tr>
                @for ($k = 1; $k <= 4; $k++)
                    <tr><td>نتائج {{ Workspace::quarterName($k) }}</td><td class="small">PDF · DOCX · XLSX · CSV</td><td>@include('partials.download', ['scope' => 'results_quarter', 'plan' => $plan, 'quarter' => $k])</td></tr>
                @endfor
                <tr><td><b>حزمة السنة كاملة</b><div class="small muted">الخطة + تقارير الأرباع الأربعة + النتائج السنوية + بيانات CSV + فهرس الملفات</div></td><td class="small">ZIP</td><td>@include('partials.download', ['scope' => 'year_package', 'plan' => $plan])</td></tr>
            @endif
        </tbody>
    </table></div>
    <p class="hint">نتيجة هدف أو مؤشر أو مشروع: استخدم زر التنزيل داخل صفحة الهدف أو المؤشر أو المشروع.</p>
</div>

<div class="card">
    <h2>سجل الملفات المنشأة</h2>
    @if ($exports->isEmpty())
        <p class="muted small">لم تُنشأ ملفات بعد.</p>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الملف</th><th>النوع</th><th>الربع</th><th>النسخة</th><th>حالة الخطة</th><th>أنشأه</th><th>التاريخ</th><th>مرات التنزيل</th></tr></thead>
            <tbody>
            @foreach ($exports as $e)
                <tr>
                    <td><a href="{{ route('exports.download', $e) }}">{{ $e->file_name }}</a></td>
                    <td class="small">{{ $e->scopeLabel() }}</td>
                    <td class="small">{{ $e->quarter ? 'ر' . $e->quarter : '—' }}</td>
                    <td class="n">{{ $e->plan_version_no ? 'v' . $e->plan_version_no : 'مسودة' }}</td>
                    <td class="small">{{ \App\Models\Plan::STATUSES[$e->plan_status] ?? '—' }}</td>
                    <td class="small">{{ $e->user->name }}</td>
                    <td class="n">{{ Fmt::dt($e->created_at) }}</td>
                    <td class="n">{{ $e->download_count }}</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
