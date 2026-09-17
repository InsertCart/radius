<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    {{-- The admin panel must never be indexed, whatever the SEO settings say. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('title', 'Dashboard') &middot; {{ setting('site_name', config('app.name')) }}</title>

    @include('partials.favicon')

    {{-- Endpoints the Alpine components need. Kept as data rather than built
         into the bundle so the panel keeps working under a sub-directory. --}}
    @php
        $cmsJs = [
            'csrfToken' => csrf_token(),
            'mediaUploadUrl' => route('admin.media.store'),
            'mediaBrowseUrl' => route('admin.media.browse'),
            'storageUrl' => rtrim(Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url(''), '/'),
        ];
    @endphp
    <script>
        window.CMS = @json($cmsJs);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-800 antialiased">
<div x-data="{ sidebarOpen: false }" class="min-h-full lg:flex">

    {{-- Mobile backdrop --}}
    <div x-show="sidebarOpen" x-cloak @click="sidebarOpen = false"
         class="fixed inset-0 z-30 bg-slate-900/50 lg:hidden"></div>

    <aside x-bind:class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
           class="fixed inset-y-0 left-0 z-40 w-64 transform overflow-y-auto bg-slate-900 text-slate-300 transition-transform lg:static lg:shrink-0 lg:translate-x-0 lg:overflow-visible">
        {{-- on="dark": this sidebar is bg-slate-900 at every colour scheme, so
             it takes the light-ink mark. This is the one surface in the product
             where the ordinary dark logo would be invisible. --}}
        <div class="flex h-16 items-center gap-2.5 border-b border-slate-800 px-5">
            <x-site-logo on="dark" small class="h-8 w-auto shrink-0" />
            <span class="truncate font-semibold text-white">{{ setting('site_name', config('app.name')) }}</span>
        </div>

        <nav class="space-y-6 px-3 py-5 text-sm">
            @foreach ($adminNavigation as $section => $links)
                @continue(empty($links))
                <div>
                    <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ $section }}</p>
                    <ul class="space-y-0.5">
                        @foreach ($links as $link)
                            <li>
                                <a href="{{ $link['url'] }}"
                                   @class([
                                       'group flex items-center gap-3 rounded-lg px-3 py-2 transition',
                                       'bg-indigo-600 text-white shadow-sm shadow-indigo-900/40' => $link['active'],
                                       'hover:bg-slate-800 hover:text-white' => ! $link['active'],
                                   ])>
                                    {{-- The icon carries the colour shift on hover so the
                                         row reads as one target rather than two. --}}
                                    <x-admin.icon :name="$link['icon']"
                                                  class="transition {{ $link['active'] ? 'text-white' : 'text-slate-500 group-hover:text-indigo-400' }}" />
                                    <span class="flex-1 truncate">{{ $link['label'] }}</span>
                                    @if (! empty($link['badge']))
                                        <span class="rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-semibold text-white">
                                            {{ $link['badge'] }}
                                        </span>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="min-w-0 lg:flex-1">
        <header class="sticky top-0 z-20 flex h-16 items-center gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur sm:px-6">
            <button @click="sidebarOpen = !sidebarOpen" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 lg:hidden" aria-label="Toggle menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>

            <div class="min-w-0 flex-1">
                <h1 class="truncate text-base font-semibold text-slate-900">@yield('title', 'Dashboard')</h1>
                @hasSection('subtitle')
                    <p class="truncate text-xs text-slate-500">@yield('subtitle')</p>
                @endif
            </div>

            <a href="{{ url('/') }}" target="_blank" rel="noopener"
               class="hidden rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50 sm:block">
                View site
            </a>

            <div x-data="{ open: false }" class="relative">
                <button @click="open = !open" class="flex items-center gap-2 rounded-lg p-1.5 hover:bg-slate-100">
                    <span class="grid h-8 w-8 place-items-center rounded-full bg-slate-800 text-xs font-semibold text-white">
                        {{ auth()->user()->initials() }}
                    </span>
                </button>
                <div x-show="open" x-cloak @click.outside="open = false"
                     class="absolute right-0 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                    <div class="px-3 py-2">
                        <p class="truncate text-sm font-medium text-slate-900">{{ auth()->user()->name }}</p>
                        <p class="truncate text-xs text-slate-500">{{ auth()->user()->email }}</p>
                    </div>
                    <hr class="my-1 border-slate-100">
                    <a href="{{ route('two-factor.setup') }}" class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-slate-50">
                        Two-factor auth
                        @if (auth()->user()->hasTwoFactorEnabled())
                            <span class="text-xs text-emerald-600">On</span>
                        @else
                            <span class="text-xs text-rose-600">Off</span>
                        @endif
                    </a>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <main class="p-4 sm:p-6">
            <x-admin.alerts />
            @yield('content')
        </main>

        <footer class="px-6 pb-8 pt-2 text-center text-xs text-slate-400">
            {{ config('cms.name') }} v{{ cms_version() }} &middot; Laravel {{ app()->version() }}
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
