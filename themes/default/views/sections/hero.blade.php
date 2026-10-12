{{-- The homepage banner. Empty fields fall back to the site's own name and
     tagline, and each button to the shop or blog while that module is on. --}}
@php
    $title = trim((string) ($settings['title'] ?? '')) ?: setting('site_name', config('app.name'));
    $text = trim((string) ($settings['text'] ?? '')) ?: (string) setting('site_tagline');

    $primaryLabel = trim((string) ($settings['primary_label'] ?? 'Browse the shop'));
    $primaryUrl = safe_url($settings['primary_url'] ?? '') ?: (modules()->enabled('shop') ? route('shop.index') : '');

    $secondaryLabel = trim((string) ($settings['secondary_label'] ?? 'Read the blog'));
    $secondaryUrl = safe_url($settings['secondary_url'] ?? '') ?: (modules()->enabled('blog') ? route('blog.index') : '');

    $showButtons = (bool) ($settings['show_buttons'] ?? true);
@endphp

<section class="border-b border-slate-100 bg-gradient-to-b from-slate-50 to-white">
    <div class="mx-auto max-w-6xl px-4 py-20 text-center sm:py-28">
        <h1 class="mx-auto max-w-3xl text-4xl font-bold tracking-tight text-slate-900 sm:text-5xl">
            {{ $title }}
        </h1>
        @if ($text !== '')
            <p class="mx-auto mt-5 max-w-2xl text-lg text-slate-600">{{ $text }}</p>
        @endif

        @if ($showButtons)
            <div class="mt-8 flex flex-wrap justify-center gap-3">
                @if ($primaryLabel !== '' && $primaryUrl !== '')
                    <a href="{{ $primaryUrl }}" class="rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
                        {{ $primaryLabel }}
                    </a>
                @endif
                @if ($secondaryLabel !== '' && $secondaryUrl !== '')
                    <a href="{{ $secondaryUrl }}" class="rounded-lg border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        {{ $secondaryLabel }}
                    </a>
                @endif
            </div>
        @endif
    </div>
</section>
