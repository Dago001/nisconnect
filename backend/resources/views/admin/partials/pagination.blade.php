@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="pager" role="navigation" aria-label="Pagination">
    <div class="muted small">
        @if($paginator->total() > 0)
            Showing {{ number_format($paginator->firstItem()) }}–{{ number_format($paginator->lastItem()) }} of {{ number_format($paginator->total()) }}
        @endif
    </div>
    @if ($paginator->hasPages())
    <div class="pages">
        <a href="{{ $paginator->previousPageUrl() }}" class="{{ $paginator->onFirstPage() ? 'disabled' : '' }}" rel="prev" aria-label="Previous">‹</a>
        @foreach ($elements as $element)
            @if (is_string($element))<span class="gap">…</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())<span class="cur" aria-current="page">{{ $page }}</span>
                    @else<a href="{{ $url }}">{{ $page }}</a>@endif
                @endforeach
            @endif
        @endforeach
        <a href="{{ $paginator->nextPageUrl() }}" class="{{ $paginator->hasMorePages() ? '' : 'disabled' }}" rel="next" aria-label="Next">›</a>
    </div>
    @endif
</nav>
@endif
