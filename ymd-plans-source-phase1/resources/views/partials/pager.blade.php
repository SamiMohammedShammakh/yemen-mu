@if ($paginator->hasPages())
    <nav class="pager" aria-label="الصفحات">
        @if ($paginator->onFirstPage())<span class="muted">السابق</span>@else<a href="{{ $paginator->previousPageUrl() }}">السابق</a>@endif
        @foreach ($elements as $element)
            @if (is_string($element))<span>{{ $element }}</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="on">{{ $page }}</span>@else<a href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">التالي</a>@else<span class="muted">التالي</span>@endif
    </nav>
@endif
