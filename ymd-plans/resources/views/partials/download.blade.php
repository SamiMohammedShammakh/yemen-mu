{{--
    زر تنزيل: يفتح المعاينة أولًا (المنصب، السنة، الربع، النسخة، تاريخ البيانات، حالة الاعتماد).
    $scope, $plan (اختياري), $subject (اختياري), $quarter (اختياري), $year (لتقرير شامل), $label
--}}
@php
    $formats = \App\Services\Export\ExportService::SCOPE_FORMATS[$scope];
    $fid = 'dl-' . $scope . '-' . ($subject ?? $plan?->id ?? 'y') . '-' . \Illuminate\Support\Str::random(4);
@endphp
<form class="dl-form no-print" method="get" action="{{ route('exports.preview') }}">
    <input type="hidden" name="scope" value="{{ $scope }}">
    @isset($plan)<input type="hidden" name="plan_id" value="{{ $plan->id }}">@endisset
    @isset($subject)<input type="hidden" name="subject_id" value="{{ $subject }}">@endisset
    @isset($quarter)<input type="hidden" name="quarter" value="{{ $quarter }}">@endisset
    @isset($year)<input type="hidden" name="planning_year_id" value="{{ $year }}">@endisset
    @if (count($formats) > 1)
        <label for="{{ $fid }}" class="small muted" style="white-space:nowrap">الصيغة</label>
        <select id="{{ $fid }}" name="format">
            @foreach ($formats as $f)<option value="{{ $f }}">{{ strtoupper($f) }}</option>@endforeach
        </select>
    @else
        <input type="hidden" name="format" value="{{ $formats[0] }}">
    @endif
    <button class="btn sec sm" title="معاينة ثم تنزيل">⬇ {{ $label ?? 'تنزيل' }}</button>
</form>
