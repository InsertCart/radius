@php
    $drawerCategories = collect();

    if (modules()->enabled('shop')) {
        $drawerCategories = \App\Models\Category::shop()
            ->active()
            ->roots()
            ->where('show_in_menu', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->where('show_in_menu', true)])
            ->orderBy('sort_order')
            ->get();
    }

    $drawerMenu = collect($siteMenus['header'] ?? [])->filter(fn ($item) => $item->isVisible());
@endphp

<div class="sf-scrim" data-scrim></div>

{{-- Mobile navigation ---------------------------------------------------- --}}
<div class="sf-drawer sf-drawer--left" id="sf-nav-drawer" role="dialog" aria-modal="true" aria-label="Menu">
    <div class="sf-drawer__head">
        <span>Browse</span>
        <button type="button" class="sf-drawer__close" data-drawer-close aria-label="Close menu">
            @include('theme::partials.icon', ['name' => 'close'])
        </button>
    </div>

    <div class="sf-drawer__body">
        <ul class="sf-mnav">
            @if ($drawerMenu->isNotEmpty())
                @foreach ($drawerMenu as $item)
                    @php $children = $item->children->filter(fn ($child) => $child->isVisible()); @endphp
                    <li>
                        @if ($children->isEmpty())
                            <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a>
                        @else
                            <button type="button" class="sf-mnav__toggle" data-mnav-toggle aria-expanded="false">
                                {{ $item->label }}
                                @include('theme::partials.icon', ['name' => 'chevron-down'])
                            </button>
                            <div class="sf-mnav__sub">
                                <a href="{{ $item->resolveUrl() }}">{{ $item->label }} home</a>
                                @foreach ($children as $child)
                                    <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}">{{ $child->label }}</a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach
            @else
                @foreach ($drawerCategories as $category)
                    <li>
                        @if ($category->children->isEmpty())
                            <a href="{{ $category->url() }}">{{ $category->name }}</a>
                        @else
                            <button type="button" class="sf-mnav__toggle" data-mnav-toggle aria-expanded="false">
                                {{ $category->name }}
                                @include('theme::partials.icon', ['name' => 'chevron-down'])
                            </button>
                            <div class="sf-mnav__sub">
                                <a href="{{ $category->url() }}">Shop all {{ $category->name }}</a>
                                @foreach ($category->children as $child)
                                    <a href="{{ $child->url() }}">{{ $child->name }}</a>
                                @endforeach
                            </div>
                        @endif
                    </li>
                @endforeach

                @module('shop')
                    <li><a href="{{ route('shop.index') }}">All products</a></li>
                @endmodule
                @module('blog')
                    <li><a href="{{ route('blog.index') }}">Blog</a></li>
                @endmodule
                @module('contact')
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                @endmodule
            @endif

            <li>
                <a href="{{ auth()->check() ? route('account.dashboard') : route('login') }}">
                    {{ auth()->check() ? 'Your account' : 'Sign in' }}
                </a>
            </li>
        </ul>
    </div>
</div>

{{-- Saved items ---------------------------------------------------------- --}}
@module('shop')
    <div class="sf-drawer sf-drawer--right" id="sf-saved-drawer" role="dialog" aria-modal="true" aria-label="Saved items">
        <div class="sf-drawer__head">
            <span>Saved items</span>
            <button type="button" class="sf-drawer__close" data-drawer-close aria-label="Close saved items">
                @include('theme::partials.icon', ['name' => 'close'])
            </button>
        </div>

        <div class="sf-drawer__body">
            {{-- Filled in by the theme runtime from this browser's storage. --}}
            <p class="sf-saved__empty" data-saved-empty>
                Nothing saved yet. Tap the heart on any product to keep it here.
            </p>
            <ul data-saved-list></ul>
            <p class="sf-help sf-mt">Saved items live in this browser only and are not shared with your account.</p>
        </div>
    </div>
@endmodule
