@if ($paginator->hasPages())
    <nav class="mt-10 flex items-center justify-center gap-1" role="navigation">
        @if ($paginator->onFirstPage())
            <span class="rounded-lg px-3 py-2 text-sm text-slate-300">Previous</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Previous</a>
        @endif

        @foreach ($elements ?? [] as $element)
            @if (is_string($element))
                <span class="px-2 text-sm text-slate-400">{{ $element }}</span>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="rounded-lg px-3 py-2 text-sm font-semibold text-white btn-brand">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-100">Next</a>
        @else
            <span class="rounded-lg px-3 py-2 text-sm text-slate-300">Next</span>
        @endif
    </nav>
@endif
