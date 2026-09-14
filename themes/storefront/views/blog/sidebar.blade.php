<aside class="sf-side">
    @if ($categories->isNotEmpty())
        <div>
            <h2 class="sf-side__title">Topics</h2>
            <ul class="sf-side__list">
                @foreach ($categories as $sidebarCategory)
                    <li>
                        <a href="{{ $sidebarCategory->url() }}">
                            <span>{{ $sidebarCategory->name }}</span>
                            <span>{{ $sidebarCategory->posts_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @module('newsletter')
        <div class="sf-panel">
            <p class="sf-panel__title">Subscribe</p>
            <p class="sf-small sf-muted">New posts, straight to your inbox.</p>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="sf-mt">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="sf-hp" aria-hidden="true">
                <label for="sf-blog-news" class="sf-sr">Email address</label>
                <input type="email" name="email" id="sf-blog-news" required placeholder="you@example.com" class="sf-input">
                <button class="sf-btn sf-btn--primary sf-btn--block sf-mt">Subscribe</button>
            </form>
        </div>
    @endmodule
</aside>
