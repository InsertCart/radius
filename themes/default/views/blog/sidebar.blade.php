<aside class="space-y-8">
    @if ($categories->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-900">Categories</h2>
            <ul class="mt-3 space-y-1.5">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ $category->url() }}" class="flex items-center justify-between text-sm text-slate-600 hover:text-brand">
                            <span>{{ $category->name }}</span>
                            <span class="text-xs text-slate-400">{{ $category->posts_count }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @module('newsletter')
        <div class="rounded-2xl bg-slate-50 p-5">
            <h2 class="text-sm font-semibold text-slate-900">Subscribe</h2>
            <p class="mt-1 text-sm text-slate-600">New posts, straight to your inbox.</p>
            <form method="POST" action="{{ route('newsletter.subscribe') }}" class="mt-3">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">
                <input type="email" name="email" required placeholder="you@example.com"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="mt-2 w-full rounded-lg px-4 py-2 text-sm font-semibold text-white btn-brand">Subscribe</button>
            </form>
        </div>
    @endmodule
</aside>
