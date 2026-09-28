{{-- عرض مضغوط لمؤشر (لوحة المنصب). $i: نتيجة المؤشر --}}
@php use App\Support\Fmt; $p = $i['period']; @endphp
<div style="padding:.7rem 0;border-bottom:1px solid var(--line-2)">
    <div class="row between">
        <a href="{{ route('indicators.show', [$i['id'], 'quarter' => $i['upto']]) }}"><b>{{ $i['name'] }}</b></a>
        @include('partials.status', ['s' => $i['status']])
    </div>
    <div class="small muted" style="margin-bottom:.4rem">{{ $i['kind_label'] }}{{ $i['unit'] ? ' · ' . $i['unit'] : '' }}{{ isset($i['effective_weight']) ? ' · الوزن ' . Fmt::num($i['effective_weight']) . '%' : '' }}</div>
    <div class="metric-grid">
        <div class="metric"><span class="l">{{ $p['compare_label'] }}</span><span class="v num">{{ Fmt::num($p['compare_target']) }}</span></div>
        <div class="metric"><span class="l">الفعلي المعتمد</span>
            @if ($p['state'] === 'measured')<span class="v num">{{ Fmt::num($p['compare_actual']) }}</span>@else<span class="badge {{ $p['state'] }}">{{ $p['state_label'] }}</span>@endif
            @if ($p['pending_count'])<span class="l" style="color:var(--info)">+{{ $p['pending_count'] }} بانتظار التحقق (غير محتسب)</span>@endif
        </div>
        <div class="metric"><span class="l">الفجوة</span><span class="small">{{ $p['gap_text'] }}</span></div>
    </div>
    <div class="grid g2" style="margin-top:.5rem;gap:.6rem">
        @include('partials.ach', ['label' => 'الإنجاز مقابل ' . $p['compare_label'], 'v' => $p['achievement'], 'key' => $i['status']['key'], 'empty' => $p['state_label']])
        @include('partials.ach', ['label' => 'الإنجاز مقابل المستهدف السنوي (' . Fmt::num($i['annual']['target']) . ')', 'v' => $i['annual']['achievement'], 'key' => 'on_track', 'empty' => $i['annual']['state_label']])
    </div>
</div>
