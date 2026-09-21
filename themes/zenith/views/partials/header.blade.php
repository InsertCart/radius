@php
    $navCategories = collect();

    if (modules()->enabled('shop')) {
        $visible = fn ($q) => $q->where('is_active', true)->where('show_in_menu', true);

        $navCategories = \App\Models\Category::shop()
            ->active()
            ->roots()
            ->where('show_in_menu', true)
            ->with(['children' => $visible])
            ->orderBy('sort_order')
            ->limit(5)
            ->get();
    }

    $headerMenu = collect($siteMenus['header'] ?? [])->filter(fn ($item) => $item->isVisible());
    $cartCount = modules()->enabled('shop') ? app(\App\Cms\Shop\CartService::class)->itemCount() : 0;

    $settings = $settings ?? [];
    $showSearch = (bool) ($settings['show_search'] ?? true);
    $showThemeToggle = (bool) ($settings['show_theme_toggle'] ?? true);
    $showWishlist = (bool) ($settings['show_wishlist'] ?? true);
    $showAccount = (bool) ($settings['show_account'] ?? true);
    $showCart = (bool) ($settings['show_cart'] ?? true);
@endphp

<header class="zn-head">
    <div class="zn-wrap zn-head__bar">
        {{-- Mobile Hamburger --}}
        <button type="button" class="zn-tool zn-tool--burger" data-drawer-open="zn-mobile-drawer" aria-label="Open mobile menu">
            @include('theme::partials.icon', ['name' => 'menu'])
        </button>

        {{-- Brand Logo --}}
        <a href="{{ url('/') }}" class="zn-logo" aria-label="{{ setting('site_name', config('app.name')) }}">
            <x-site-logo class="h-8 w-auto" />
        </a>

        {{-- Desktop Navigation --}}
        <nav class="zn-nav" aria-label="Main Navigation">
            @if ($headerMenu->isNotEmpty())
                @foreach ($headerMenu as $item)
                    @php $children = $item->children->filter(fn ($child) => $child->isVisible()); @endphp
                    <div class="zn-nav__item">
                        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}" class="zn-nav__link">
                            {{ $item->label }}
                            @if ($children->isNotEmpty())
                                <span class="zn-nav__caret">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                            @endif
                        </a>

                        @if ($children->isNotEmpty())
                            <div class="zn-dropdown">
                                @foreach ($children as $child)
                                    <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}">{{ $child->label }}</a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                @module('shop')
                    @foreach ($navCategories as $category)
                        <div class="zn-nav__item">
                            <a href="{{ $category->url() }}" class="zn-nav__link">
                                {{ $category->name }}
                                @if ($category->children->isNotEmpty())
                                    <span class="zn-nav__caret">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                                @endif
                            </a>

                            @if ($category->children->isNotEmpty())
                                <div class="zn-dropdown">
                                    <a href="{{ $category->url() }}" style="font-weight: 700; border-bottom: 1px solid var(--zn-border); margin-bottom: 4px; padding-bottom: 6px;">All {{ $category->name }}</a>
                                    @foreach ($category->children as $child)
                                        <a href="{{ $child->url() }}">{{ $child->name }}</a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach

                    @if ($navCategories->isEmpty())
                        <div class="zn-nav__item"><a href="{{ route('shop.index') }}" class="zn-nav__link">Shop</a></div>
                    @endif
                @endmodule

                @module('blog')
                    <div class="zn-nav__item"><a href="{{ route('blog.index') }}" class="zn-nav__link">Journal</a></div>
                @endmodule

                @module('contact')
                    <div class="zn-nav__item"><a href="{{ route('contact') }}" class="zn-nav__link">Concierge</a></div>
                @endmodule
            @endif
        </nav>

        {{-- Live Product Search --}}
        @if ($showSearch)
            @module('shop')
                <form class="zn-head-search" method="GET" action="{{ route('shop.index') }}" role="search" data-radius-search="product">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search collection..." autocomplete="off" aria-label="Search catalog">
                    <button type="submit" aria-label="Submit search">
                        @include('theme::partials.icon', ['name' => 'search'])
                    </button>
                </form>
                @searchScripts
            @endmodule
        @endif

        {{-- Header Controls & Tools --}}
        <div class="zn-tools">
            {{-- Dark / Light Theme Toggle Button --}}
            @if ($showThemeToggle)
                <button type="button" class="zn-tool" data-theme-toggle aria-label="Toggle dark/light mode" title="Switch appearance">
                    <span class="dark:hidden">@include('theme::partials.icon', ['name' => 'moon'])</span>
                    <span class="hidden dark:inline">@include('theme::partials.icon', ['name' => 'sun'])</span>
                </button>
            @endif

            {{-- Wishlist / Saved Items Drawer Trigger --}}
            @if ($showWishlist)
                @module('shop')
                    <button type="button" class="zn-tool" data-drawer-open="zn-saved-drawer" aria-label="Saved items" title="Saved collection">
                        @include('theme::partials.icon', ['name' => 'heart'])
                        <span class="zn-tool__badge" data-saved-count hidden>0</span>
                    </button>
                @endmodule
            @endif

            {{-- User Account / Login --}}
            @if ($showAccount)
                <a href="{{ auth()->check() ? (auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard')) : route('login') }}"
                   class="zn-tool" aria-label="{{ auth()->check() ? 'Your Account' : 'Sign In' }}" title="{{ auth()->check() ? 'Your Account' : 'Sign In' }}">
                    @include('theme::partials.icon', ['name' => 'user'])
                </a>
            @endif

            {{-- Shopping Cart --}}
            @if ($showCart)
                @module('shop')
                    <a href="{{ route('cart.index') }}" class="zn-tool" aria-label="Shopping bag" title="Shopping bag">
                        @include('theme::partials.icon', ['name' => 'bag'])
                        @if ($cartCount > 0)
                            <span class="zn-tool__badge">{{ $cartCount }}</span>
                        @endif
                    </a>
                @endmodule
            @endif
        </div>
    </div>
</header>
