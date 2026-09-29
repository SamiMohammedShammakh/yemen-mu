{{-- جدول نتائج الأهداف والمؤشرات. $results: نتيجة الخطة --}}
@php use App\Support\Fmt; @endphp
@foreach ($results['objectives'] as $o)
    <div class="card" id="obj-res-{{ $o['id'] }}">
        <div class="card-head">
            <div>
                <h3><a href="{{ route('objectives.show', [$o['id'], 'quarter' => $results['quarter']]) }}">{{ $o['title'] }}</a></h3>
                <span class="small muted">وزن الهدف {{ Fmt::num($o['weight']) }}% · {{ $o['explain'] }}</span>
            </div>
            <div class="row">
                @include('partials.status', ['s' => $o['status']])
                @if (! empty($downloadPlan))
                    @include('partials.download', ['scope' => 'objective', 'plan' => $downloadPlan, 'subject' => $o['id'], 'quarter' => $results['quarter'], 'label' => 'نتيجة الهدف'])
                @endif
            </div>
        </div>
        <div class="grid g3" style="margin-bottom:.7rem">
            @include('partials.ach', ['label' => 'إنجاز الهدف مقابل المستهدف المرحلي', 'v' => $o['period_achievement'], 'key' => $o['status']['key']])
            @include('partials.ach', ['label' => 'إنجاز الهدف مقابل المستهدف السنوي', 'v' => $o['annual_achievement'], 'key' => 'on_track'])
            <div class="ach"><span class="lbl">تغطية القياس</span><span class="v num">{{ Fmt::pct($o['coverage']) }}</span><span class="small muted">من وزن المؤشرات مقاس</span></div>
        </div>
        <div class="table-wrap">
            <table class="t">
                <thead><tr><th>المؤشر</th><th>المستهدف المرحلي</th><th>الفعلي المعتمد</th><th>الفجوة</th><th>الإنجاز المرحلي</th><th>الإنجاز السنوي</th><th>الحالة</th></tr></thead>
                <tbody>
                @foreach ($o['indicators'] as $i)
                    @include('partials.indicator-row', ['i' => $i])
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endforeach
