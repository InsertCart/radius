@props(['title' => null, 'description' => null, 'actions' => null])

<section {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200 bg-white shadow-sm']) }}>
    @if ($title || $actions)
        <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
            <div>
                @if ($title)
                    <h2 class="text-sm font-semibold text-slate-900">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="mt-0.5 text-xs text-slate-500">{{ $description }}</p>
                @endif
            </div>
            @if ($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endif
        </header>
    @endif

    <div class="{{ $attributes->get('bodyClass', 'p-5') }}">
        {{ $slot }}
    </div>
</section>
