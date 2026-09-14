@if ($paginator->hasPages())
    <nav class="sf-pager" role="navigation" aria-label="Pagination">
        @if ($paginator->onFirstPage())
            <span class="is-off">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif

        @foreach ($elements ?? [] as $element)
            @if (is_string($element))
                <span class="is-off">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="is-current" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="is-off">Next</span>
        @endif
    </nav>
@endif
