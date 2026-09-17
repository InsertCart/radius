{{-- The site-wide search results page, at /search.

     Override with views/search/index.blade.php in a theme. Available:
       $query      what was searched, trimmed
       $type       the type being paged through, or null for every type
       $types      searchable types: key => ['label' => ..., ...]
       $groups     list of ['type', 'label', 'total', 'url', 'items']
                   each item: id, type, title, url, excerpt, image, meta
       $total      matches across all groups
       $paginator  a paginator over the items when $type is set, else null --}}
@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-4xl px-4 py-14">
        <header class="mb-8">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">
                @if ($query !== '')
                    {{ __('Results for “:query”', ['query' => $query]) }}
                @else
                    {{ __('Search') }}
                @endif
            </h1>
        </header>

        @include('theme::search.form', ['type' => $type ?? 'all', 'action' => route('search'), 'value' => $query])

        @if ($query !== '' && count($types) > 1)
            <nav class="mt-6 flex flex-wrap gap-2 text-sm" aria-label="{{ __('Filter results') }}">
                <a href="{{ route('search', ['q' => $query]) }}"
                   @class(['rounded-full border px-3 py-1', 'border-slate-900 bg-slate-900 text-white' => ! $type, 'border-slate-300 text-slate-600 hover:border-slate-400' => $type])>
                    {{ __('Everything') }}
                </a>
                @foreach ($types as $key => $definition)
                    <a href="{{ route('search', ['q' => $query, 'type' => $key]) }}"
                       @class(['rounded-full border px-3 py-1', 'border-slate-900 bg-slate-900 text-white' => $type === $key, 'border-slate-300 text-slate-600 hover:border-slate-400' => $type !== $key])>
                        {{ $definition['label'] }}
                    </a>
                @endforeach
            </nav>
        @endif

        <div class="mt-10 space-y-12">
            @if ($query === '')
                <p class="text-slate-500">{{ __('Type a word or two above to search the site.') }}</p>
            @elseif ($groups === [])
                <p class="rounded-2xl border border-dashed border-slate-300 py-16 text-center text-slate-500">
                    {{ __('Nothing matches “:query”. Try fewer or different words.', ['query' => $query]) }}
                </p>
            @endif

            @foreach ($groups as $group)
                <section>
                    <div class="mb-4 flex items-baseline justify-between gap-4 border-b border-slate-200 pb-2">
                        <h2 class="text-lg font-semibold text-slate-900">
                            {{ $group['label'] }}
                            <span class="ml-1 text-sm font-normal text-slate-400">{{ $group['total'] }}</span>
                        </h2>

                        @if (! $type && $group['total'] > count($group['items']) && $group['url'])
                            <a href="{{ $group['url'] }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">
                                {{ __('See all :count', ['count' => $group['total']]) }} &rarr;
                            </a>
                        @endif
                    </div>

                    <ul class="divide-y divide-slate-100">
                        @foreach ($group['items'] as $item)
                            <li>
                                <a href="{{ $item['url'] }}" class="flex gap-4 py-4 hover:bg-slate-50">
                                    @if ($item['image'])
                                        <img src="{{ $item['image'] }}" alt="" loading="lazy"
                                             class="h-16 w-16 shrink-0 rounded-lg bg-slate-100 object-cover">
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium text-slate-900">{{ $item['title'] }}</p>
                                        @if ($item['excerpt'])
                                            <p class="mt-1 line-clamp-2 text-sm text-slate-600">{{ $item['excerpt'] }}</p>
                                        @endif
                                    </div>

                                    @if ($item['meta'])
                                        <span class="shrink-0 text-sm text-slate-500">{{ $item['meta'] }}</span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            @if ($paginator)
                {{ $paginator->links('theme::partials.pagination') }}
            @endif
        </div>
    </div>
@endsection
