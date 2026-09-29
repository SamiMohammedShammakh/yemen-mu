@php use App\Support\Workspace; @endphp
<div class="ws-title">
    <h1>{{ $plan->title() }}</h1>
    <span class="badge {{ $plan->status }}">{{ $plan->statusLabel() }}</span>
    <span class="badge outline">{{ $plan->versionLabel() }}</span>
    @isset($q)<span class="pill">{{ Workspace::quarterName($q) }}</span>@endisset
</div>
<div class="row" style="margin:-.4rem 0 1rem">
    @include('partials.plan-steps', ['plan' => $plan])
</div>
