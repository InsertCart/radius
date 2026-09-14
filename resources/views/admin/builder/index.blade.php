@extends('admin.layout')
@section('title', 'Visual builder')
@section('subtitle', 'Design pages and theme parts by dragging blocks around')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            <x-admin.card title="Theme regions"
                          :description="'Parts of the '.($theme->name ?? 'active theme').' theme you can replace with a visual layout'">
                @if (empty($regions))
                    <div class="rounded-xl border border-slate-200 bg-slate-50 p-5 text-sm text-slate-600">
                        <p class="font-medium text-slate-800">This theme does not offer any regions.</p>
                        <p class="mt-1">
                            That is not a problem &mdash; you can still build page content visually.
                            A theme opts in by declaring regions in its <code class="rounded bg-slate-200 px-1">theme.json</code>
                            and wrapping the markup it is willing to hand over:
                        </p>
<pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">&#64;region('header')
    ... the theme's own header ...
&#64;endregion</pre>
                        <p class="mt-2 text-xs text-slate-500">
                            With no layout built, the markup inside simply renders as it always has.
                        </p>
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($regions as $key => $label)
                            @php $layout = $regionLayouts[$key] ?? null; @endphp

                            <a href="{{ route('admin.builder.region', $key) }}"
                               class="group rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/40">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <p class="font-medium text-slate-900">{{ $label }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">
                                            @if ($layout && $layout->published_at)
                                                {{ $layout->widgetCount() }} widgets &middot; updated {{ $layout->updated_at->diffForHumans() }}
                                            @else
                                                Using the theme's own markup
                                            @endif
                                        </p>
                                    </div>

                                    @if ($layout && $layout->published_at)
                                        <x-admin.badge color="green">Custom</x-admin.badge>
                                    @else
                                        <x-admin.badge color="gray">Theme</x-admin.badge>
                                    @endif
                                </div>

                                <span class="mt-3 inline-block text-xs font-medium text-indigo-600 group-hover:underline">
                                    {{ $layout && $layout->published_at ? 'Edit layout' : 'Build a custom one' }} &rarr;
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-admin.card>

            <x-admin.card title="Site pages"
                          description="Pages the CMS builds for you: the cart, checkout and listings">
                @if (empty($systemAreas))
                    <p class="text-sm text-slate-500">
                        None available. These appear when the modules behind them are switched on.
                    </p>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($systemAreas as $key => $area)
                            @php $layout = $regionLayouts[$key] ?? null; @endphp

                            <a href="{{ route('admin.builder.region', $key) }}"
                               class="group rounded-xl border border-slate-200 p-4 transition hover:border-indigo-300 hover:bg-indigo-50/40">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900">{{ $area['label'] }}</p>
                                        <p class="mt-0.5 text-xs text-slate-500">{{ $area['description'] ?? '' }}</p>
                                    </div>

                                    @if ($layout && $layout->published_at)
                                        <x-admin.badge color="green">Custom</x-admin.badge>
                                    @else
                                        <x-admin.badge color="gray">Theme</x-admin.badge>
                                    @endif
                                </div>

                                <span class="mt-3 inline-block text-xs font-medium text-indigo-600 group-hover:underline">
                                    {{ $layout && $layout->published_at ? 'Edit design' : 'Redesign this page' }} &rarr;
                                </span>
                            </a>
                        @endforeach
                    </div>

                    <p class="mt-4 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">
                        The cart and checkout keep working however you arrange them &mdash; their
                        functionality is a widget you place, and the editor warns you if it ever
                        goes missing.
                    </p>
                @endif
            </x-admin.card>

            <x-admin.card title="Recently built" description="Pages, posts and products with a visual layout" bodyClass="">
                @if ($recent->isEmpty())
                    <x-admin.empty message="Nothing has been built yet. Open any page and choose &quot;Edit with builder&quot;." />
                @else
                    <ul class="divide-y divide-slate-100">
                        @foreach ($recent as $layout)
                            @php $owner = $layout->layoutable; @endphp
                            @continue(! $owner)

                            <li class="flex items-center justify-between gap-3 p-4">
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-slate-900">
                                        {{ $owner->title ?? $owner->name ?? 'Untitled' }}
                                    </p>
                                    <p class="text-xs text-slate-400">
                                        {{ class_basename($layout->layoutable_type) }}
                                        &middot; {{ $layout->widgetCount() }} widgets
                                        &middot; {{ $layout->updated_at->diffForHumans() }}
                                        @if ($layout->editor) &middot; {{ $layout->editor->name }} @endif
                                    </p>
                                </div>

                                <div class="flex shrink-0 items-center gap-2">
                                    @if ($layout->hasUnpublishedChanges())
                                        <x-admin.badge color="amber">Draft</x-admin.badge>
                                    @endif
                                    <a href="{{ route('admin.builder.edit', [
                                            'type' => strtolower(class_basename($layout->layoutable_type)),
                                            'id' => $layout->layoutable_id,
                                       ]) }}"
                                       class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                        Edit
                                    </a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-admin.card>
        </div>

        <div class="space-y-6">
            <x-admin.card title="How it works">
                <ol class="space-y-3 text-sm text-slate-600">
                    <li class="flex gap-2">
                        <span class="font-semibold text-slate-400">1.</span>
                        <span>Open a page, post or product and choose <strong>Edit with builder</strong>.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-semibold text-slate-400">2.</span>
                        <span>Pick a section layout, then drag widgets into its columns.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-semibold text-slate-400">3.</span>
                        <span>Click anything to change its content, colours, spacing and fonts.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-semibold text-slate-400">4.</span>
                        <span>Switch to tablet or mobile to adjust how it looks on smaller screens.</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="font-semibold text-slate-400">5.</span>
                        <span>Press <strong>Publish</strong>. Until you do, your work stays as a private draft.</span>
                    </li>
                </ol>
            </x-admin.card>

            <x-admin.card title="Your theme still works">
                <p class="text-sm text-slate-600">
                    The builder sits alongside your theme rather than replacing it. Anything you
                    have not built visually keeps rendering from the template exactly as before,
                    and turning the builder off for a page brings its original content straight
                    back.
                </p>
            </x-admin.card>

            @if ($presets->isNotEmpty())
                <x-admin.card title="Saved sections" :description="$presets->count().' reusable blocks'">
                    <ul class="space-y-2 text-sm">
                        @foreach ($presets as $preset)
                            <li class="flex items-center justify-between gap-2">
                                <span class="truncate text-slate-700">{{ $preset->name }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ $preset->category }}</span>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
