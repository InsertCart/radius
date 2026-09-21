<div class="zn-drawer-backdrop" aria-hidden="true"></div>

{{-- Mobile Navigation Drawer --}}
<div class="zn-drawer zn-drawer--left" id="zn-mobile-drawer" role="dialog" aria-modal="true" aria-label="Mobile menu">
    <div class="zn-drawer__head">
        <h2 class="zn-drawer__title">Menu</h2>
        <button type="button" class="zn-drawer__close" data-drawer-close aria-label="Close menu">
            @include('theme::partials.icon', ['name' => 'close'])
        </button>
    </div>
    <div class="zn-drawer__body">
        {{-- Mobile Search Form --}}
        @module('shop')
            <form method="GET" action="{{ route('shop.index') }}" role="search" style="margin-bottom: 24px; position: relative;">
                <input type="search" name="q" placeholder="Search collection..." autocomplete="off"
                       style="width: 100%; height: 42px; padding: 0 14px 0 38px; border-radius: var(--zn-radius-pill); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); box-sizing: border-box;">
                <span style="position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--zn-muted);">
                    @include('theme::partials.icon', ['name' => 'search'])
                </span>
            </form>
        @endmodule

        {{-- Mobile Menu Links --}}
        <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 6px;">
            @php
                $mobileHeaderMenu = collect($siteMenus['header'] ?? [])->filter(fn ($item) => $item->isVisible());
            @endphp

            @if ($mobileHeaderMenu->isNotEmpty())
                @foreach ($mobileHeaderMenu as $item)
                    <li>
                        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}"
                           style="display: block; padding: 10px 14px; font-size: 16px; font-weight: 600; border-radius: var(--zn-radius-sm); color: var(--zn-text);">
                            {{ $item->label }}
                        </a>
                    </li>
                @endforeach
            @else
                <li><a href="{{ url('/') }}" style="display: block; padding: 10px 14px; font-size: 16px; font-weight: 600; color: var(--zn-text);">Home</a></li>
                @module('shop')
                    <li><a href="{{ route('shop.index') }}" style="display: block; padding: 10px 14px; font-size: 16px; font-weight: 600; color: var(--zn-text);">Shop All</a></li>
                @endmodule
                @module('blog')
                    <li><a href="{{ route('blog.index') }}" style="display: block; padding: 10px 14px; font-size: 16px; font-weight: 600; color: var(--zn-text);">Journal</a></li>
                @endmodule
                @module('contact')
                    <li><a href="{{ route('contact') }}" style="display: block; padding: 10px 14px; font-size: 16px; font-weight: 600; color: var(--zn-text);">Contact</a></li>
                @endmodule
            @endif
        </ul>

        {{-- Account / Sign In CTA in Drawer --}}
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid var(--zn-border);">
            @if (auth()->check())
                <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard') }}"
                   class="zn-btn zn-btn--secondary zn-btn--block" style="margin-bottom: 10px;">
                    @include('theme::partials.icon', ['name' => 'user'])
                    {{ auth()->user()->isStaff() ? 'Admin Panel' : 'My Account' }}
                </a>
            @else
                <a href="{{ route('login') }}" class="zn-btn zn-btn--primary zn-btn--block">Sign In / Register</a>
            @endif
        </div>
    </div>
</div>

{{-- Saved Items (Wishlist) Drawer --}}
@module('shop')
<div class="zn-drawer" id="zn-saved-drawer" role="dialog" aria-modal="true" aria-label="Saved items">
    <div class="zn-drawer__head">
        <div style="display: flex; align-items: center; gap: 8px;">
            <h2 class="zn-drawer__title">Saved Collection</h2>
            <span class="zn-badge zn-badge--accent" data-saved-count>0</span>
        </div>
        <button type="button" class="zn-drawer__close" data-drawer-close aria-label="Close saved items">
            @include('theme::partials.icon', ['name' => 'close'])
        </button>
    </div>
    <div class="zn-drawer__body" data-saved-items-container>
        {{-- Filled dynamically via theme.js --}}
        <div style="text-align: center; padding: 40px 10px; color: var(--zn-muted);">
            <p style="font-size: 15px; margin-bottom: 8px;">Your saved list is empty.</p>
            <p style="font-size: 13px;">Click the heart icon on any product to save it for later.</p>
        </div>
    </div>
    <div style="padding: 18px 24px; border-top: 1px solid var(--zn-border); background: var(--zn-surface);">
        <a href="{{ route('shop.index') }}" class="zn-btn zn-btn--primary zn-btn--block">Explore More Pieces</a>
    </div>
</div>
@endmodule
