@php
    use App\Support\Access;
    use App\Support\Workspace;
    $u = auth()->user();
    $hasPlans = Access::positions($u)->isNotEmpty() || Access::hasGlobalView($u) || Access::activeDelegation($u);
    $isReviewer = Access::canReview($u);
    $pendingVerify = $isReviewer ? \App\Models\ProgressUpdate::where('status', 'pending')->count() : 0;
    $pendingDecisions = Access::canApprove($u)
        ? \App\Models\Plan::where('status', 'recommended')->count() + \App\Models\ChangeRequest::where('status', 'pending')->count()
        : 0;
    $route = request()->route()?->getName() ?? '';
    $on = fn (...$names) => collect($names)->contains(fn ($n) => str_starts_with($route, $n)) ? 'on' : '';
    $closed = $ctx->year?->quarters?->where('status', 'closed')->pluck('number')->all() ?? [];
@endphp
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'نظام الخطط السنوية') — جمعية المهندسين اليمنيين</title>
    <link rel="icon" href="{{ asset('img/logo-mark.png') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v=3">
</head>
<body>
<header class="topbar">
    <button class="menu-toggle" type="button" aria-label="القائمة" onclick="document.body.classList.toggle('nav-open')">☰</button>
    <a class="brand" href="{{ route('home') }}"><img src="{{ asset('img/logo-mark.png') }}" alt="شعار الجمعية"><span>نظام الخطط السنوية والمتابعة</span></a>
    <div class="spacer"></div>
    @if ($hasPlans)
        <form class="search-box" action="{{ route('search') }}" method="get" role="search">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="بحث في خططي…" aria-label="بحث">
        </form>
    @endif
    <a class="icon-btn" href="{{ route('alerts.index') }}" title="التنبيهات" aria-label="التنبيهات">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 8a6 6 0 1 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
        @if ($unreadAlerts)<span class="dot">{{ $unreadAlerts }}</span>@endif
    </a>
    <form method="post" action="{{ route('logout') }}" class="inline">@csrf
        <button class="icon-btn" title="تسجيل الخروج" aria-label="تسجيل الخروج" style="cursor:pointer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17-5-5 5-5"/><path d="M11 12H21" transform="rotate(180 16 12)"/></svg>
        </button>
    </form>
</header>

<div class="shell">
    <nav class="sidebar" aria-label="القائمة الرئيسية">
        <div class="who">
            <b>{{ $u->name }}</b>
            <span class="small muted">{{ Access::positions($u)->pluck('name')->implode('، ') ?: ($u->is_system_admin ? 'مدير النظام التقني' : 'بلا منصب') }}</span>
            @if (Access::isExecutive($u))<div><span class="badge info">عضو الإدارة التنفيذية</span></div>@endif
            @if (Access::activeDelegation($u) && ! Access::isPresident($u))<div><span class="badge warn">مفوّض بالاعتماد</span></div>@endif
        </div>

        @if ($ctx->position())
            <div class="grp">مساحة عمل المنصب</div>
            <a class="nav {{ $on('workspace') }}" href="{{ route('workspace') }}">لوحة {{ $ctx->position()->name }}</a>
        @endif
        @if ($hasPlans)
            <a class="nav {{ $on('plans.', 'objectives.', 'indicators.', 'projects.') }}" href="{{ route('plans.index') }}">{{ Access::hasGlobalView($u) ? 'جميع الخطط' : 'خطتي' }}</a>
        @endif

        @if ($isReviewer || Access::canSeeExecutiveBoard($u))
            <div class="grp">المتابعة الشاملة</div>
        @endif
        @if ($isReviewer)
            <a class="nav {{ $on('planning.') }}" href="{{ route('planning.center') }}">مركز التخطيط والمتابعة</a>
            <a class="nav {{ $on('verification.') }}" href="{{ route('verification.index') }}">التحقق من الأدلة @if ($pendingVerify)<span class="cnt">{{ $pendingVerify }}</span>@endif</a>
        @endif
        @if (Access::canSeeExecutiveBoard($u))
            <a class="nav {{ $on('executive') }}" href="{{ route('executive') }}">لوحة الرئيس والإدارة التنفيذية @if ($pendingDecisions)<span class="cnt">{{ $pendingDecisions }}</span>@endif</a>
        @endif
        @if ($hasPlans)
            <a class="nav {{ $on('change-requests.') }}" href="{{ route('change-requests.index') }}">طلبات التعديل @if (! Access::canSeeExecutiveBoard($u) && $pendingDecisions)<span class="cnt">{{ $pendingDecisions }}</span>@endif</a>
        @endif
        @if (Access::canManageYears($u) || Access::hasGlobalView($u))
            <a class="nav {{ $on('years.') }}" href="{{ route('years.index') }}">السنوات والأرباع</a>
        @endif

        @if ($hasPlans)
            <div class="grp">الملفات</div>
            <a class="nav {{ $on('reports.', 'exports.') }}" href="{{ route('reports.index') }}">التقارير والتنزيلات</a>
        @endif
        <a class="nav {{ $on('alerts.') }}" href="{{ route('alerts.index') }}">التنبيهات @if ($unreadAlerts)<span class="cnt">{{ $unreadAlerts }}</span>@endif</a>

        @if (Access::canManageAccounts($u) || Access::canSeeAudit($u))
            <div class="grp">الإدارة</div>
        @endif
        @if (Access::canManageAccounts($u))
            <a class="nav {{ $on('admin.users') }}" href="{{ route('admin.users.index') }}">الحسابات والمناصب</a>
            <a class="nav {{ $on('admin.access') }}" href="{{ route('admin.access.index') }}">الإدارة التنفيذية والتفويض</a>
        @endif
        @if (Access::canSeeAudit($u))
            <a class="nav {{ $on('audit.') }}" href="{{ route('audit.index') }}">سجل التدقيق</a>
        @endif
    </nav>

    <main class="main">
        @unless (View::hasSection('no_context'))
        <div class="context no-print">
            @if (count($ctx->workspaces) > 1)
                <form method="post" action="{{ route('context.update') }}">@csrf
                    <label for="ctx-ws">مساحة العمل</label>
                    <select id="ctx-ws" name="ws" onchange="this.form.submit()">
                        @foreach ($ctx->workspaces as $w)
                            <option value="{{ $w['key'] }}" @selected(($ctx->current['key'] ?? '') === $w['key'])>{{ $w['label'] }}</option>
                        @endforeach
                    </select>
                </form>
            @elseif ($ctx->current)
                <span><span class="small muted">مساحة العمل:</span> <b>{{ $ctx->current['label'] }}</b></span>
            @endif
            @if ($ctx->years->isNotEmpty())
                <form method="post" action="{{ route('context.update') }}">@csrf
                    <label for="ctx-year">السنة</label>
                    <select id="ctx-year" name="year_id" onchange="this.form.submit()">
                        @foreach ($ctx->years as $y)
                            <option value="{{ $y->id }}" @selected($ctx->year?->id === $y->id)>{{ $y->year }}</option>
                        @endforeach
                    </select>
                </form>
                <form method="post" action="{{ route('context.update') }}">@csrf
                    <label>الربع</label>
                    <div class="qseg" role="group" aria-label="اختيار الربع">
                        @for ($i = 1; $i <= 4; $i++)
                            <button name="quarter" value="{{ $i }}" class="{{ (int) request('quarter', $ctx->quarter) === $i ? 'on' : '' }}" title="{{ Workspace::quarterName($i) }}{{ in_array($i, $closed) ? ' (مغلق)' : '' }}">ر{{ $i }}@if (in_array($i, $closed))<span class="lock"> 🔒</span>@endif</button>
                        @endfor
                    </div>
                </form>
            @else
                <span class="muted small">لم تُنشأ أي سنة تخطيط بعد.</span>
            @endif
        </div>
        @endunless

        @include('partials.flash')
        @yield('content')
    </main>
</div>
<script>
    document.addEventListener('click', function (e) {
        var c = e.target.closest('[data-confirm]');
        if (c && !confirm(c.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
</script>
@stack('scripts')
</body>
</html>
