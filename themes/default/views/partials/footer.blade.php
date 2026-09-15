<footer class="mt-16 border-t border-slate-200 bg-slate-50">
    <div class="mx-auto max-w-6xl px-4 py-12">
        <div class="grid gap-8 md:grid-cols-4">
            <div class="md:col-span-2">
                <p class="text-lg font-bold text-slate-900">{{ setting('site_name', config('app.name')) }}</p>
                @if (setting('site_tagline'))
                    <p class="mt-2 max-w-sm text-sm text-slate-600">{{ setting('site_tagline') }}</p>
                @endif

                <div class="mt-4 space-y-1 text-sm text-slate-500">
                    @if (setting('site_email'))
                        <p><a href="mailto:{{ setting('site_email') }}" class="hover:text-slate-900">{{ setting('site_email') }}</a></p>
                    @endif
                    @if (setting('site_phone'))
                        <p><a href="tel:{{ setting('site_phone') }}" class="hover:text-slate-900">{{ setting('site_phone') }}</a></p>
                    @endif
                    @if (setting('site_address'))
                        <p class="whitespace-pre-line">{{ setting('site_address') }}</p>
                    @endif
                </div>

                @php
                    $socials = array_filter([
                        'Facebook' => setting('social_facebook'),
                        'Instagram' => setting('social_instagram'),
                        'X' => setting('social_twitter'),
                        'LinkedIn' => setting('social_linkedin'),
                        'YouTube' => setting('social_youtube'),
                    ]);
                @endphp

                @if ($socials)
                    <div class="mt-4 flex flex-wrap gap-3">
                        @foreach ($socials as $label => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
                               class="text-sm text-slate-500 hover:text-slate-900">{{ $label }}</a>
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <p class="text-sm font-semibold text-slate-900">Explore</p>
                <ul class="mt-3 space-y-2 text-sm">
                    @forelse ($siteMenus['footer'] ?? [] as $item)
                        @continue(! $item->isVisible())
                        <li><a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}" class="text-slate-600 hover:text-slate-900">{{ $item->label }}</a></li>
                    @empty
                        <li><a href="{{ url('/') }}" class="text-slate-600 hover:text-slate-900">Home</a></li>
                        @module('blog')<li><a href="{{ route('blog.index') }}" class="text-slate-600 hover:text-slate-900">Blog</a></li>@endmodule
                        @module('shop')<li><a href="{{ route('shop.index') }}" class="text-slate-600 hover:text-slate-900">Shop</a></li>@endmodule
                        @module('contact')<li><a href="{{ route('contact') }}" class="text-slate-600 hover:text-slate-900">Contact</a></li>@endmodule
                    @endforelse
                </ul>
            </div>

            @module('newsletter')
                <div>
                    <p class="text-sm font-semibold text-slate-900">Newsletter</p>
                    <p class="mt-2 text-sm text-slate-600">Occasional updates. No spam.</p>
                    <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3">
                        @csrf
                        {{-- Honeypot: hidden from people, irresistible to bots. --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                        <input type="email" name="email" required placeholder="you@example.com"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="mt-2 w-full rounded-lg px-4 py-2 text-sm font-semibold text-white btn-brand">
                            Subscribe
                        </button>
                    </form>
                </div>
            @endmodule
        </div>

        <div class="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-6 text-xs text-slate-500">
            <p>&copy; {{ date('Y') }} {{ setting('site_name', config('app.name')) }}. All rights reserved.</p>
            <p>Powered by <a href="https://www.insertcart.com" target="_blank" rel="noopener noreferrer" class="text-slate-500 hover:text-slate-900">{{ config('cms.name') }}</a></p>
        </div>
    </div>
</footer>
