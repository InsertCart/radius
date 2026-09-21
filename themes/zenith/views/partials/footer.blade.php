@php
    $settings = $settings ?? [];

    $footerMenu = collect($siteMenus['footer'] ?? [])->filter(fn ($item) => $item->isVisible());
    $collectionsMenu = collect($siteMenus['footer_collections'] ?? [])->filter(fn ($item) => $item->isVisible());
    $legalMenu = collect($siteMenus['footer_legal'] ?? [])->filter(fn ($item) => $item->isVisible());

    $tagline = trim((string) ($settings['tagline'] ?? '')) ?: (setting('site_tagline') ?: 'Elegance defined by purpose and craft. Curated timeless aesthetics for modern lifestyles.');
    $badgeOne = trim((string) ($settings['badge_one'] ?? 'EST. 2026'));
    $badgeTwo = trim((string) ($settings['badge_two'] ?? 'CARBON NEUTRAL'));
    $col2Title = trim((string) ($settings['col2_title'] ?? 'Explore'));
    $col3Title = trim((string) ($settings['col3_title'] ?? 'Collections'));

    // Column 2 Link resolution
    $col2Raw = (string) ($settings['col2_links'] ?? '');
    $col2MenuSlug = trim((string) ($settings['col2_menu'] ?? 'footer'));
    $col2Items = [];

    if (filled($col2Raw)) {
        foreach (explode("\n", $col2Raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line, 2);
            $lbl = trim($parts[0]);
            $lnk = isset($parts[1]) ? trim($parts[1]) : '#';
            if ($lbl !== '') {
                $col2Items[] = ['label' => $lbl, 'url' => $lnk];
            }
        }
    } elseif ($col2MenuSlug !== '' && isset($siteMenus[$col2MenuSlug])) {
        foreach (collect($siteMenus[$col2MenuSlug])->filter(fn ($item) => $item->isVisible()) as $item) {
            $col2Items[] = ['label' => $item->label, 'url' => $item->resolveUrl(), 'target' => $item->target];
        }
    } elseif ($col2MenuSlug !== '') {
        $foundMenu = \App\Models\Menu::where('slug', $col2MenuSlug)->first();
        if ($foundMenu) {
            foreach ($foundMenu->items()->where('is_visible', true)->orderBy('sort_order')->get() as $item) {
                $col2Items[] = ['label' => $item->label, 'url' => $item->resolveUrl(), 'target' => $item->target];
            }
        }
    }

    // Column 3 Link resolution
    $col3Source = trim((string) ($settings['col3_source'] ?? 'categories'));
    $col3Raw = (string) ($settings['col3_links'] ?? '');
    $col3MenuSlug = trim((string) ($settings['col3_menu'] ?? 'footer_collections'));
    $col3Items = [];

    if ($col3Source === 'custom' && filled($col3Raw)) {
        foreach (explode("\n", $col3Raw) as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line, 2);
            $lbl = trim($parts[0]);
            $lnk = isset($parts[1]) ? trim($parts[1]) : '#';
            if ($lbl !== '') {
                $col3Items[] = ['label' => $lbl, 'url' => $lnk];
            }
        }
    } elseif ($col3Source === 'menu' && $col3MenuSlug !== '') {
        if (isset($siteMenus[$col3MenuSlug])) {
            foreach (collect($siteMenus[$col3MenuSlug])->filter(fn ($item) => $item->isVisible()) as $item) {
                $col3Items[] = ['label' => $item->label, 'url' => $item->resolveUrl(), 'target' => $item->target];
            }
        } else {
            $foundMenu = \App\Models\Menu::where('slug', $col3MenuSlug)->first();
            if ($foundMenu) {
                foreach ($foundMenu->items()->where('is_visible', true)->orderBy('sort_order')->get() as $item) {
                    $col3Items[] = ['label' => $item->label, 'url' => $item->resolveUrl(), 'target' => $item->target];
                }
            }
        }
    }

    $showNewsletter = (bool) ($settings['show_newsletter'] ?? true);
    $newsletterTitle = trim((string) ($settings['newsletter_title'] ?? 'Private Circle'));
    $newsletterText = trim((string) ($settings['newsletter_text'] ?? 'Sign up to receive private seasonal dispatches, product drops, and exclusive releases.'));
    $newsletterButton = trim((string) ($settings['newsletter_button'] ?? 'Join'));
    $copyright = trim((string) ($settings['copyright'] ?? ''));
@endphp

<footer class="zn-footer">
    <div class="zn-wrap">
        <div class="zn-footer__grid">
            {{-- Column 1: Brand & Philosophy --}}
            <div class="zn-footer__brand">
                <a href="{{ url('/') }}" class="zn-logo">
                    <x-site-logo class="h-8 w-auto" />
                </a>
                <p>
                    {{ $tagline }}
                </p>
                @if ($badgeOne !== '' || $badgeTwo !== '')
                    <div style="display: flex; gap: 10px; margin-top: 14px;">
                        @if ($badgeOne !== '')
                            <span class="zn-badge zn-badge--accent">{{ $badgeOne }}</span>
                        @endif
                        @if ($badgeTwo !== '')
                            <span class="zn-badge zn-badge--emerald">{{ $badgeTwo }}</span>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Column 2: Navigation / Quick Links --}}
            <div>
                <p class="zn-footer__title">{{ $col2Title }}</p>
                <ul class="zn-footer__links">
                    @if (!empty($col2Items))
                        @foreach ($col2Items as $item)
                            <li><a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    @elseif ($footerMenu->isNotEmpty())
                        @foreach ($footerMenu as $item)
                            <li><a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a></li>
                        @endforeach
                    @else
                        @module('shop')
                            <li><a href="{{ route('shop.index') }}">Collection</a></li>
                        @endmodule
                        @module('blog')
                            <li><a href="{{ route('blog.index') }}">Journal</a></li>
                        @endmodule
                        @module('contact')
                            <li><a href="{{ route('contact') }}">Concierge & FAQ</a></li>
                        @endmodule
                    @endif
                </ul>
            </div>

            {{-- Column 3: Collections / Categories --}}
            <div>
                <p class="zn-footer__title">{{ $col3Title }}</p>
                <ul class="zn-footer__links">
                    @if (!empty($col3Items))
                        @foreach ($col3Items as $item)
                            <li><a href="{{ $item['url'] }}" target="{{ $item['target'] ?? '_self' }}">{{ $item['label'] }}</a></li>
                        @endforeach
                    @elseif ($col3Source === 'menu' && $collectionsMenu->isNotEmpty())
                        @foreach ($collectionsMenu as $item)
                            <li><a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a></li>
                        @endforeach
                    @else
                        @module('shop')
                            @php
                                $footerCats = \App\Models\Category::shop()->active()->roots()->limit(6)->get();
                            @endphp
                            @foreach ($footerCats as $cat)
                                <li><a href="{{ $cat->url() }}">{{ $cat->name }}</a></li>
                            @endforeach
                        @endmodule
                    @endif
                </ul>
            </div>

            {{-- Column 4: Newsletter & Society --}}
            @if ($showNewsletter)
                <div>
                    <p class="zn-footer__title">{{ $newsletterTitle }}</p>
                    <p style="font-size: 13px; color: var(--zn-muted); margin-bottom: 14px; line-height: 1.5;">
                        {{ $newsletterText }}
                    </p>
                    @module('newsletter')
                        <form method="POST" action="{{ route('newsletter.subscribe') }}" style="display: flex; gap: 8px;">
                            @csrf
                            <input type="email" name="email" placeholder="Your email address" required
                                   style="flex: 1; height: 40px; padding: 0 14px; border-radius: var(--zn-radius-pill); border: 1px solid var(--zn-border); background: var(--zn-surface); color: var(--zn-text); font-size: 13px;">
                            <button type="submit" class="zn-btn zn-btn--primary zn-btn--sm">{{ $newsletterButton }}</button>
                        </form>
                    @endmodule
                </div>
            @endif
        </div>

        {{-- Footer Bottom --}}
        <div class="zn-footer__bottom">
            <div>
                @if ($copyright !== '')
                    {{ $copyright }}
                @else
                    &copy; {{ date('Y') }} {{ setting('site_name', config('app.name')) }}. All rights reserved.
                @endif
            </div>

            <div style="display: flex; gap: 18px;">
                @if ($legalMenu->isNotEmpty())
                    @foreach ($legalMenu as $item)
                        <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a>
                    @endforeach
                @else
                    <span>Privacy Policy</span>
                    <span>Terms of Service</span>
                    <span>Cookie Settings</span>
                @endif
            </div>
        </div>
    </div>
</footer>
