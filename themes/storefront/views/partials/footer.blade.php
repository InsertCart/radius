@php
    $socials = array_filter([
        'facebook' => setting('social_facebook'),
        'instagram' => setting('social_instagram'),
        'twitter' => setting('social_twitter'),
        'linkedin' => setting('social_linkedin'),
        'youtube' => setting('social_youtube'),
        'whatsapp' => whatsapp_url(),
    ]);

    // Four link columns. The first falls back to the site's own sections so a
    // brand-new install still has a usable footer.
    $columns = [
        'Shop' => collect($siteMenus['footer'] ?? [])->filter(fn ($item) => $item->isVisible()),
        'Useful links' => collect($siteMenus['footer_help'] ?? [])->filter(fn ($item) => $item->isVisible()),
        'About us' => collect($siteMenus['footer_about'] ?? [])->filter(fn ($item) => $item->isVisible()),
        'Store & support' => collect($siteMenus['footer_support'] ?? [])->filter(fn ($item) => $item->isVisible()),
    ];

    $popularCategories = collect();

    if (modules()->enabled('shop')) {
        $popularCategories = \App\Models\Category::shop()
            ->active()
            ->orderBy('sort_order')
            ->limit(12)
            ->get();
    }
@endphp

<footer class="sf-foot">
    <div class="sf-foot__top">
        <div class="sf-foot__brand">
            <a href="{{ url('/') }}" class="sf-logo">
                <x-site-logo on="light" />
            </a>

            @if (setting('site_tagline'))
                <p class="sf-foot__blurb">{{ setting('site_tagline') }}</p>
            @endif

            <div class="sf-foot__blurb">
                @if (setting('site_email'))
                    <p><a href="mailto:{{ setting('site_email') }}">{{ setting('site_email') }}</a></p>
                @endif
                @if (setting('site_phone'))
                    <p><a href="tel:{{ setting('site_phone') }}">{{ setting('site_phone') }}</a></p>
                @endif
                @if (setting('site_address'))
                    <p class="sf-pre">{{ setting('site_address') }}</p>
                @endif
            </div>

            @module('newsletter')
                <form method="POST" action="{{ route('newsletter.subscribe') }}" class="sf-foot__news">
                    @csrf
                    {{-- Honeypot: hidden from people, irresistible to bots. --}}
                    <input type="text" name="website" tabindex="-1" autocomplete="off" class="sf-hp" aria-hidden="true">
                    <label for="sf-news" class="sf-sr">Email address</label>
                    <input type="email" name="email" id="sf-news" required placeholder="you@example.com">
                    <button class="sf-btn sf-btn--dark sf-btn--sm">Subscribe</button>
                </form>
            @endmodule
        </div>

        @foreach ($columns as $heading => $items)
            <div class="sf-foot__col">
                <h3>{{ $heading }}</h3>
                <ul>
                    @forelse ($items as $item)
                        <li><a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}">{{ $item->label }}</a></li>
                    @empty
                        @if ($heading === 'Shop')
                            <li><a href="{{ url('/') }}">Home</a></li>
                            @module('shop')<li><a href="{{ route('shop.index') }}">All products</a></li>@endmodule
                            @module('blog')<li><a href="{{ route('blog.index') }}">Blog</a></li>@endmodule
                            @module('contact')<li><a href="{{ route('contact') }}">Contact</a></li>@endmodule
                        @else
                            <li class="sf-small">Add a "{{ $heading }}" menu in the admin panel.</li>
                        @endif
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>

    @if ($popularCategories->isNotEmpty())
        <div class="sf-foot__searches">
            <h3>Popular searches</h3>
            <ul>
                @foreach ($popularCategories as $category)
                    <li><a href="{{ $category->url() }}">{{ $category->name }}</a></li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="sf-foot__base">
        <div>
            <p>&copy; {{ date('Y') }} {{ setting('site_name', config('app.name')) }}. All rights reserved.</p>

            @if ($socials)
                <div class="sf-social">
                    @foreach ($socials as $network => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($network) }}">
                            @include('theme::partials.icon', ['name' => $network])
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</footer>
