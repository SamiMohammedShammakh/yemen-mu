@extends('layouts.app')
@section('title', 'الأهداف الاستراتيجية')
@section('content')
<div class="ws-title"><h1>الأهداف الاستراتيجية للجمعية</h1><span class="pill">{{ $goals->where('status', 'active')->count() }} هدف فعّال</span></div>
<p class="small muted">مرجع مشترك لكل المناصب: يُربط كل هدف في الخطة السنوية بهدف استراتيجي. متى وُجد هدف استراتيجي فعّال يغطي سنة الخطة، لا تُرسل الخطة قبل ربط كل أهدافها.</p>

<div class="grid {{ $canManage ? 'g-2-1' : '' }}">
    <div class="card">
        @if ($goals->isEmpty())
            <div class="empty">لا توجد أهداف استراتيجية بعد.@if ($canManage) أضف أول هدف من النموذج.@endif</div>
        @else
            <div class="table-wrap"><table class="t">
                <thead><tr><th>الرقم</th><th>الهدف</th><th>الفترة</th><th>الحالة</th><th>أهداف خطط مرتبطة{{ $canManage ? '' : ' (في خطتك)' }}</th></tr></thead>
                <tbody>
                @foreach ($goals as $g)
                    <tr>
                        <td class="n"><b>{{ $g->ref }}</b></td>
                        <td><a href="{{ route('strategic-goals.show', $g) }}"><b>{{ $g->title }}</b></a>@if ($g->description)<div class="small muted">{{ \Illuminate\Support\Str::limit($g->description, 140) }}</div>@endif</td>
                        <td class="n">{{ $g->from_year ?? '…' }} – {{ $g->to_year ?? '…' }}</td>
                        <td><span class="badge {{ $g->status === 'active' ? 'ok' : 'closed' }}">{{ $g->statusLabel() }}</span></td>
                        <td class="n">{{ $counts[$g->id] ?? 0 }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </div>

    @if ($canManage)
    <div class="card">
        <h2>إضافة هدف استراتيجي</h2>
        <form method="post" action="{{ route('strategic-goals.store') }}">@csrf
            <div class="field"><label class="f" for="g-t">عنوان الهدف</label><input id="g-t" type="text" name="title" value="{{ old('title') }}" required></div>
            <div class="field"><label class="f" for="g-d">الوصف</label><textarea id="g-d" name="description" rows="3">{{ old('description') }}</textarea></div>
            <div class="form-grid">
                <div class="field"><label class="f" for="g-f">من سنة (اختياري)</label><input id="g-f" type="number" name="from_year" min="2020" max="2100" value="{{ old('from_year') }}"></div>
                <div class="field"><label class="f" for="g-to">إلى سنة (اختياري)</label><input id="g-to" type="number" name="to_year" min="2020" max="2100" value="{{ old('to_year') }}"></div>
            </div>
            <button class="btn">إضافة</button>
            <p class="hint">يُمنح الهدف رقمًا مرجعيًا تلقائيًا (SG-01، SG-02…) لا يتغير.</p>
        </form>
    </div>
    @endif
</div>
@endsection
