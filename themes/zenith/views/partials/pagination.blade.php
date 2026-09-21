@if ($paginator->hasPages())
    <nav style="display: flex; align-items: center; justify-content: center; gap: 8px; margin: 40px 0;" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="zn-btn zn-btn--ghost zn-btn--sm" style="opacity: 0.5; pointer-events: none;">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="zn-btn zn-btn--ghost zn-btn--sm">&larr; Previous</a>
        @endif

        @foreach ($elements ?? [] as $element)
            @if (is_string($element))
                <span style="padding: 0 6px; color: var(--zn-muted);">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="zn-btn zn-btn--primary zn-btn--sm" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="zn-btn zn-btn--ghost zn-btn--sm">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="zn-btn zn-btn--ghost zn-btn--sm">Next &rarr;</a>
        @else
            <span class="zn-btn zn-btn--ghost zn-btn--sm" style="opacity: 0.5; pointer-events: none;">Next</span>
        @endif
    </nav>
@endif
