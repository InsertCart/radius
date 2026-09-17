@php
    // Top-level shop categories drive the mega menu when the owner has not
    // built a header menu of their own. Children are eager loaded so the
    // panel costs one extra query rather than one per column.
    $navCategories = collect();

    if (modules()->enabled('shop')) {
        $visible = fn ($q) => $q->where('is_active', true)->where('show_in_menu', true);

        $navCategories = \App\Models\Category::shop()
            ->active()
            ->roots()
            ->where('show_in_menu', true)
            ->with(['children' => $visible, 'children.children' => $visible])
            ->orderBy('sort_order')
            ->limit(6)
            ->get();
    }

    $headerMenu = collect($siteMenus['header'] ?? [])->filter(fn ($item) => $item->isVisible());
    $cartCount = modules()->enabled('shop') ? app(\App\Cms\Shop\CartService::class)->itemCount() : 0;
@endphp

<header class="sf-head">
    <div class="sf-head__bar">
        <button type="button" class="sf-tool sf-tool--burger" data-drawer-open="sf-nav-drawer" aria-label="Open menu">
            @include('theme::partials.icon', ['name' => 'menu'])
        </button>

        <a href="{{ url('/') }}" class="sf-logo">
            {{-- The bar is --sf-accent (yellow) whatever the colour scheme. --}}
            <x-site-logo on="light" />
        </a>

        <nav class="sf-nav" aria-label="Main">
            @if ($headerMenu->isNotEmpty())
                @foreach ($headerMenu as $item)
                    @php $children = $item->children->filter(fn ($child) => $child->isVisible()); @endphp

                    <div class="sf-nav__item" @if ($children->isNotEmpty()) data-mega @endif>
                        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}" class="sf-nav__link">
                            {{ $item->label }}
                            @if ($children->isNotEmpty())
                                <span class="sf-nav__caret">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                            @endif
                        </a>

                        @if ($children->isNotEmpty())
                            <div class="sf-mega">
                                <div class="sf-mega__panel">
                                    @foreach ($children->chunk(8) as $column)
                                        <div>
                                            <ul class="sf-mega__list">
                                                @foreach ($column as $child)
                                                    <li><a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}">{{ $child->label }}</a></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            @else
                @foreach ($navCategories as $category)
                    <div class="sf-nav__item" @if ($category->children->isNotEmpty()) data-mega @endif>
                        <a href="{{ $category->url() }}" class="sf-nav__link">
                            {{ $category->name }}
                            @if ($category->children->isNotEmpty())
                                <span class="sf-nav__caret">@include('theme::partials.icon', ['name' => 'chevron-down'])</span>
                            @endif
                        </a>

                        @if ($category->children->isNotEmpty())
                            <div class="sf-mega">
                                <div class="sf-mega__panel">
                                    @if ($category->children->contains(fn ($child) => $child->children->isNotEmpty()))
                                        {{-- Three levels deep: each sub-category heads its own column. --}}
                                        @foreach ($category->children as $group)
                                            <div>
                                                <p class="sf-mega__title">{{ $group->name }}</p>
                                                <ul class="sf-mega__list">
                                                    <li><a href="{{ $group->url() }}" class="sf-mega__all">Shop all {{ $group->name }}</a></li>
                                                    @foreach ($group->children as $child)
                                                        <li><a href="{{ $child->url() }}">{{ $child->name }}</a></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    @else
                                        {{-- Two levels: one heading, then as many columns as it takes. --}}
                                        @foreach ($category->children->chunk(8) as $column)
                                            <div>
                                                <p class="sf-mega__title">{{ $loop->first ? $category->name : ' ' }}</p>
                                                <ul class="sf-mega__list">
                                                    @if ($loop->first)
                                                        <li><a href="{{ $category->url() }}" class="sf-mega__all">Shop all {{ $category->name }}</a></li>
                                                    @endif
                                                    @foreach ($column as $child)
                                                        <li><a href="{{ $child->url() }}">{{ $child->name }}</a></li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach

                @module('shop')
                    @if ($navCategories->isEmpty())
                        <div class="sf-nav__item"><a href="{{ route('shop.index') }}" class="sf-nav__link">Shop</a></div>
                    @endif
                @endmodule

                @module('blog')
                    <div class="sf-nav__item"><a href="{{ route('blog.index') }}" class="sf-nav__link">Blog</a></div>
                @endmodule

                @module('contact')
                    <div class="sf-nav__item"><a href="{{ route('contact') }}" class="sf-nav__link">Contact</a></div>
                @endmodule
            @endif
        </nav>

        @module('shop')
            <form class="sf-search" method="GET" action="{{ route('shop.index') }}" role="search" data-radius-search="product">
                <label for="sf-q" class="sf-sr">Search products</label>
                <input type="search" name="q" id="sf-q" value="{{ request('q') }}"
                       placeholder="Search by title, author or keyword" autocomplete="off">
                <button type="submit" aria-label="Search">@include('theme::partials.icon', ['name' => 'search'])</button>
            </form>
        @endmodule

        <div class="sf-tools">
            @module('shop')
                <button type="button" class="sf-tool" data-drawer-open="sf-saved-drawer" aria-label="Saved items">
                    @include('theme::partials.icon', ['name' => 'heart'])
                    <span class="sf-tool__count" data-saved-count hidden>0</span>
                </button>
            @endmodule

            <a href="{{ auth()->check() ? (auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard')) : route('login') }}"
               class="sf-tool" aria-label="{{ auth()->check() ? 'Your account' : 'Sign in' }}">
                @include('theme::partials.icon', ['name' => 'user'])
            </a>

            @module('shop')
                <a href="{{ route('cart.index') }}" class="sf-tool" aria-label="Your bag">
                    @include('theme::partials.icon', ['name' => 'bag'])
                    @if ($cartCount > 0)
                        <span class="sf-tool__count">{{ $cartCount }}</span>
                    @endif
                </a>
            @endmodule
        </div>
    </div>

    @module('shop')
        {{-- The search field is hidden in the masthead on small screens, so it
             gets its own row rather than disappearing entirely. --}}
        <div class="sf-head__msearch">
            <form method="GET" action="{{ route('shop.index') }}" role="search" class="sf-search" data-radius-search="product">
                <label for="sf-q-mobile" class="sf-sr">Search products</label>
                <input type="search" name="q" id="sf-q-mobile" value="{{ request('q') }}" placeholder="Search the store" autocomplete="off">
                <button type="submit" aria-label="Search">@include('theme::partials.icon', ['name' => 'search'])</button>
            </form>
        </div>

        {{-- Live results for both boxes above. Styled by the --radius-search-*
             properties in theme.css. --}}
        @searchScripts
    @endmodule
</header>
