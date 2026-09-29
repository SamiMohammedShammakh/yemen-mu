@if (session('ok'))
    <div class="flash ok" role="status">
        {{ session('ok') }}
        @if (session('download'))
            <div style="margin-top:.4rem"><a class="btn sm" href="{{ session('download') }}">تنزيل الملف الآن</a></div>
        @endif
    </div>
@endif
@if (session('warn'))
    <div class="flash warn">{{ session('warn') }}</div>
@endif
@if ($errors->any())
    <div class="flash err" role="alert">
        <b>تعذّر تنفيذ الطلب:</b>
        <ul>
            @foreach ($errors->all() as $e)
                <li>{{ $e }}</li>
            @endforeach
        </ul>
    </div>
@endif
