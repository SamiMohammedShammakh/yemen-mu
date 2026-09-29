<html dir="rtl" lang="ar">
<head>
<style>
    body { font-family: cairo; font-size: 9.5pt; color: #16191c; direction: rtl; }
    h1 { font-size: 16pt; color: #1E9C80; margin: 0 0 6pt; }
    h2 { font-size: 12.5pt; color: #16191c; margin: 12pt 0 5pt; border-bottom: 0.6pt solid #cdeee5; padding-bottom: 2pt; }
    h3 { font-size: 10.5pt; color: #1E9C80; margin: 9pt 0 4pt; }
    p { margin: 0 0 5pt; }
    table.kv { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    table.kv td { border: 0.5pt solid #d7dcdf; padding: 3pt 5pt; vertical-align: top; }
    table.kv td.k { background: #E9F7F3; font-weight: bold; width: 28%; }
    table.t { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    table.t th { background: #2BBB9A; color: #fff; font-weight: bold; padding: 3pt 4pt; border: 0.5pt solid #2BBB9A; font-size: 8.5pt; text-align: right; }
    table.t td { border: 0.5pt solid #d7dcdf; padding: 3pt 4pt; vertical-align: top; font-size: 8.5pt; }
    table.t tr.odd td { background: #f8faf9; }
    table.wide th, table.wide td { font-size: 7.8pt; }
    .note { color: #666; font-size: 8.5pt; }
    .warn { color: #B45309; font-weight: bold; border: 0.8pt solid #f5d8a8; background: #FEF3E2; padding: 4pt 6pt; margin-bottom: 6pt; }
    ul { margin: 0 0 6pt; padding-right: 14pt; }
    li { margin-bottom: 2pt; }
</style>
</head>
<body>
@foreach ($blocks as $b)
    @switch($b[0])
        @case('h1')<h1>{{ $b[1] }}</h1>@break
        @case('h2')<h2>{{ $b[1] }}</h2>@break
        @case('h3')<h3>{{ $b[1] }}</h3>@break
        @case('p')<p>{!! nl2br(e((string) $b[1])) !!}</p>@break
        @case('note')<p class="note">{{ $b[1] }}</p>@break
        @case('warn')<div class="warn">{{ $b[1] }}</div>@break
        @case('list')
            <ul>@foreach ($b[1] as $line)<li>{{ $line }}</li>@endforeach</ul>
            @break
        @case('kv')
            <table class="kv">
                @foreach ($b[1] as [$k, $v])
                    <tr><td class="k">{{ $k }}</td><td>{!! nl2br(e((string) ($v ?? '—'))) !!}</td></tr>
                @endforeach
            </table>
            @break
        @case('table')
            <table class="t {{ ($b[3] ?? '') === 'wide' ? 'wide' : '' }}" repeat_header="1">
                <thead><tr>@foreach ($b[1] as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($b[2] as $row)
                    <tr class="{{ $loop->odd ? '' : 'odd' }}">@foreach ($row as $cell)<td>{{ ($cell === null || $cell === '') ? '—' : $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($b[1]) }}">لا توجد بيانات.</td></tr>
                @endforelse
                </tbody>
            </table>
            @break
    @endswitch
@endforeach
</body>
</html>
