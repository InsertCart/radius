<header x-data="{ open: false }" class="sticky top-0 z-40 border-b border-slate-200 bg-white/95 backdrop-blur">
    <div class="mx-auto flex h-16 max-w-6xl items-center gap-4 px-4">
        <a href="{{ url('/') }}" class="flex shrink-0 items-center gap-2">
            {{-- on="light": this bar is bg-white/95 at every colour scheme, so
                 the dark-ink logo is always the readable one here. Falls back to
                 the shipped logo until one is uploaded in Settings -> General. --}}
            <x-site-logo on="light" class="h-10 w-auto" />
        </a>

        {{-- Desktop navigation. Falls back to a sensible default when the site
             owner has not built a 'header' menu yet. --}}
        <nav class="hidden flex-1 items-center gap-6 md:flex">
            @forelse ($siteMenus['header'] ?? [] as $item)
                @continue(! $item->isVisible())
                <div class="relative" x-data="{ sub: false }" @mouseenter="sub = true" @mouseleave="sub = false">
                    <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}"
                       class="text-sm font-medium text-slate-600 transition hover:text-slate-900">
                        {{ $item->label }}
                    </a>
                    @if ($item->children->isNotEmpty())
                        <div x-show="sub" x-cloak class="absolute left-0 top-full w-48 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
                            @foreach ($item->children as $child)
                                @continue(! $child->isVisible())
                                <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}"
                                   class="block rounded-lg px-3 py-2 text-sm text-slate-600 hover:bg-slate-50">{{ $child->label }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>
            @empty
                @module('blog')
                    <a href="{{ route('blog.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Blog</a>
                @endmodule
                @module('shop')
                    <a href="{{ route('shop.index') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Shop</a>
                @endmodule
                @module('contact')
                    <a href="{{ route('contact') }}" class="text-sm font-medium text-slate-600 hover:text-slate-900">Contact</a>
                @endmodule
            @endforelse
        </nav>

        <div class="ml-auto flex items-center gap-2">
            @module('shop')
                @php $cartCount = app(\App\Cms\Shop\CartService::class)->itemCount(); @endphp
                <a href="{{ route('cart.index') }}" class="relative rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Cart">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/>
                    </svg>
                    @if ($cartCount > 0)
                        <span class="absolute -right-0.5 -top-0.5 grid h-4 min-w-4 place-items-center rounded-full px-1 text-[10px] font-bold text-white btn-brand">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endmodule

            @auth
                <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard') }}"
                   class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:block">
                    {{ auth()->user()->isStaff() ? 'Admin' : 'Account' }}
                </a>
            @else
                <a href="{{ route('login') }}" class="hidden rounded-lg px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-100 sm:block">
                    Sign in
                </a>
            @endauth

            <button @click="open = ! open" class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Menu">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
        </div>
    </div>

    {{-- Mobile navigation --}}
    <nav x-show="open" x-cloak class="border-t border-slate-200 bg-white px-4 py-3 md:hidden">
        <div class="space-y-1">
            @forelse ($siteMenus['header'] ?? [] as $item)
                @continue(! $item->isVisible())
                <a href="{{ $item->resolveUrl() }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    {{ $item->label }}
                </a>
            @empty
                @module('blog')<a href="{{ route('blog.index') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Blog</a>@endmodule
                @module('shop')<a href="{{ route('shop.index') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Shop</a>@endmodule
                @module('contact')<a href="{{ route('contact') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">Contact</a>@endmodule
            @endforelse

            <a href="{{ auth()->check() ? (auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard')) : route('login') }}"
               class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-50">
                {{ auth()->check() ? 'My account' : 'Sign in' }}
            </a>
        </div>
    </nav>
</header>
