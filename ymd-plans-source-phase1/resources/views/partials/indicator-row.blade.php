{{-- صف مؤشر في جدول النتائج. $i: نتيجة المؤشر من Calculator --}}
@php use App\Support\Fmt; $p = $i['period']; $unit = $i['unit']; @endphp
<tr>
    <td>
        <a href="{{ route('indicators.show', [$i['id'], 'quarter' => $i['upto']]) }}"><b>{{ $i['name'] }}</b></a>
        <div class="small muted">{{ $i['kind_label'] }}{{ $unit ? ' · ' . $unit : '' }}{{ isset($i['effective_weight']) ? ' · الوزن ' . Fmt::num($i['effective_weight']) . '%' : '' }}</div>
    </td>
    <td>
        <div class="small muted">{{ $p['compare_label'] }}</div>
        <span class="num">{{ Fmt::num($p['compare_target']) }}</span>
    </td>
    <td>
        @if ($p['state'] === 'measured')
            <span class="num"><b>{{ Fmt::num($p['compare_actual']) }}</b></span>
            @if ($p['adjusted'])<div><span class="badge warn">تسوية معتمدة</span></div>@endif
        @else
            <span class="badge {{ $p['state'] }}">{{ $p['state_label'] }}</span>
        @endif
        @if ($p['pending_count'])
            <div class="small" style="color:var(--info)">+{{ $p['pending_count'] }} بانتظار التحقق (غير محتسب)</div>
        @endif
    </td>
    <td class="small">{{ $p['gap_text'] }}</td>
    <td style="min-width:150px">
        @include('partials.ach', ['label' => 'مقابل ' . $p['compare_label'], 'v' => $p['achievement'], 'key' => $i['status']['key'], 'empty' => $p['state_label']])
    </td>
    <td style="min-width:140px">
        @include('partials.ach', ['label' => 'مقابل المستهدف السنوي (' . Fmt::num($i['annual']['target']) . ')', 'v' => $i['annual']['achievement'], 'key' => 'on_track', 'empty' => $i['annual']['state_label']])
    </td>
    <td>@include('partials.status', ['s' => $i['status']])</td>
</tr>
