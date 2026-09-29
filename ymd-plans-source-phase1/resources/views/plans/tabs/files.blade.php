@php use App\Support\Fmt; @endphp
<div class="card">
    <div class="card-head"><h2>المرفقات والأدلة</h2><span class="small muted">كل تنزيل يُتحقق فيه من صلاحيتك على هذه الخطة ويُسجَّل</span></div>
    @if ($files->isEmpty())
        <div class="empty">لا توجد مرفقات. تُرفع الأدلة مع تحديثات الإنجاز.</div>
    @else
        <div class="table-wrap"><table class="t">
            <thead><tr><th>الملف</th><th>مرتبط بـ</th><th>حالة التحقق</th><th>الحجم</th><th>رفعه</th><th>بصمة SHA-256</th></tr></thead>
            <tbody>
            @foreach ($files as $f)
                <tr>
                    <td><a href="{{ route('attachments.show', $f) }}">📎 {{ $f->original_name }}</a></td>
                    <td class="small">@if ($f->progressUpdate)تحديث #{{ $f->progressUpdate->id }} — {{ $f->progressUpdate->indicator?->name ?? $f->progressUpdate->task?->title }}@else — @endif</td>
                    <td>@if ($f->progressUpdate)<span class="badge {{ $f->progressUpdate->status }}">{{ $f->progressUpdate->statusLabel() }}</span>@endif</td>
                    <td class="n">{{ $f->humanSize() }}</td>
                    <td class="small">{{ $f->uploader->name }}<br>{{ Fmt::dt($f->created_at) }}</td>
                    <td class="small"><code title="{{ $f->sha256 }}">{{ \Illuminate\Support\Str::limit($f->sha256, 12, '…') }}</code></td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
