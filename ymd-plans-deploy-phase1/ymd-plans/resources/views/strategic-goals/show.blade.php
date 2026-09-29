@extends('layouts.app')
@section('title', $goal->ref . ' — ' . $goal->title)
@php use App\Support\Fmt; @endphp
@section('content')
<div class="ws-title">
    <h1><span class="num">{{ $goal->ref }}</span> {{ $goal->title }}</h1>
    <span class="badge {{ $goal->status === 'active' ? 'ok' : 'closed' }}">{{ $goal->statusLabel() }}</span>
    <a class="btn sec sm right" href="{{ route('strategic-goals.index') }}">← الأهداف الاستراتيجية</a>
</div>

<div class="grid {{ $canManage ? 'g-2-1' : '' }}">
    <div>
        <div class="card">
            <table class="kv">
                <tr><th>الوصف</th><td>{!! nl2br(e($goal->description ?: '—')) !!}</td></tr>
                <tr><th>الفترة</th><td class="num">{{ $goal->from_year ?? '…' }} – {{ $goal->to_year ?? '…' }}</td></tr>
                <tr><th>أُنشئ</th><td>{{ $goal->creator?->name ?? '—' }} · {{ Fmt::dt($goal->created_at) }}</td></tr>
            </table>
        </div>
        <div class="card">
            <h2>أهداف الخطط المرتبطة{{ $canManage ? '' : ' (في خطتك)' }}</h2>
            @if ($objectives->isEmpty())
                <p class="muted small">لا توجد أهداف خطط مرتبطة بهذا الهدف{{ $canManage ? '' : ' في خطتك' }}.</p>
            @else
                <div class="table-wrap"><table class="t">
                    <thead><tr><th>الرقم</th><th>هدف الخطة</th><th>الخطة</th><th>الوزن</th><th>حالة الخطة</th></tr></thead>
                    <tbody>
                    @foreach ($objectives as $o)
                        <tr>
                            <td class="n">{{ $o->ref }}</td>
                            <td><a href="{{ route('objectives.show', $o) }}">{{ $o->title }}</a></td>
                            <td class="small">{{ $o->plan->position->name }} {{ $o->plan->year->year }}</td>
                            <td class="n">{{ Fmt::num($o->weight) }}%</td>
                            <td><span class="badge {{ $o->plan->status }}">{{ $o->plan->statusLabel() }}</span></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </div>
    </div>

    @if ($canManage)
    <div>
        <div class="card">
            <h2>تعديل الهدف</h2>
            <form method="post" action="{{ route('strategic-goals.update', $goal) }}">@csrf @method('put')
                <div class="field"><label class="f" for="e-t">العنوان</label><input id="e-t" type="text" name="title" value="{{ old('title', $goal->title) }}" required></div>
                <div class="field"><label class="f" for="e-d">الوصف</label><textarea id="e-d" name="description" rows="3">{{ old('description', $goal->description) }}</textarea></div>
                <div class="form-grid">
                    <div class="field"><label class="f" for="e-f">من سنة</label><input id="e-f" type="number" name="from_year" value="{{ old('from_year', $goal->from_year) }}"></div>
                    <div class="field"><label class="f" for="e-to">إلى سنة</label><input id="e-to" type="number" name="to_year" value="{{ old('to_year', $goal->to_year) }}"></div>
                    <div class="field"><label class="f" for="e-s">الترتيب</label><input id="e-s" type="number" name="sort" min="0" value="{{ old('sort', $goal->sort) }}"></div>
                </div>
                <button class="btn sec">حفظ</button>
            </form>
            <hr>
            <form method="post" action="{{ route('strategic-goals.status', $goal) }}" class="inline">@csrf
                @if ($goal->status === 'active')
                    <input type="hidden" name="status" value="archived"><button class="btn sec sm" data-confirm="أرشفة الهدف؟ تبقى الروابط السابقة ولا يظهر للربط الجديد.">أرشفة</button>
                @else
                    <input type="hidden" name="status" value="active"><button class="btn sm">إعادة تفعيل</button>
                @endif
            </form>
            @if ($linkedTotal === 0)
                <form method="post" action="{{ route('strategic-goals.destroy', $goal) }}" class="inline">@csrf @method('delete')
                    <button class="btn danger sm" data-confirm="حذف الهدف نهائيًا؟ (غير مرتبط بأي خطة)">حذف</button>
                </form>
            @else
                <p class="hint">مرتبط بـ {{ $linkedTotal }} هدف خطة؛ لا يُحذف ويمكن أرشفته.</p>
            @endif
        </div>
        <div class="card">
            <h2>سجل التغييرات</h2>
            <ul class="timeline">
                @forelse ($history as $h)
                    <li><code>{{ $h->action }}</code> — {{ $h->user?->name }}
                        @if ($h->old_values)<div class="small diff-old">{{ json_encode($h->old_values, JSON_UNESCAPED_UNICODE) }}</div>@endif
                        @if ($h->new_values)<div class="small diff-new">{{ json_encode($h->new_values, JSON_UNESCAPED_UNICODE) }}</div>@endif
                        <div class="when">{{ Fmt::dt($h->created_at) }}</div></li>
                @empty
                    <li class="muted small">لا توجد تغييرات.</li>
                @endforelse
            </ul>
        </div>
    </div>
    @endif
</div>
@endsection
