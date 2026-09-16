@php $reviews = $product->approvedReviews; @endphp

<section class="cb-reviews">
    @if (filled($settings['heading'] ?? null))
        <h2 class="cb-reviews__heading">{{ $settings['heading'] }}</h2>
    @endif

    <div class="cb-reviews__grid {{ ! empty($settings['show_form']) ? 'cb-reviews__grid--form' : '' }}">
        <div class="cb-reviews__list">
            @forelse ($reviews as $review)
                <article class="cb-review">
                    <div class="cb-review__head">
                        <strong>{{ $review->displayName() }}</strong>
                        <span class="cb-reviews__stars" aria-label="{{ $review->rating }} out of 5">{{ str_repeat('★', $review->rating) }}</span>
                    </div>
                    @if ($review->title)
                        <p class="cb-review__title">{{ $review->title }}</p>
                    @endif
                    @if ($review->body)
                        <p class="cb-review__body">{{ $review->body }}</p>
                    @endif
                    <p class="cb-review__meta">
                        {{ format_date($review->created_at) }}
                        @if ($review->verified_purchase) &middot; Verified purchase @endif
                    </p>
                </article>
            @empty
                <p class="cb-review__empty">{{ $settings['empty_text'] }}</p>
            @endforelse
        </div>

        @if (! empty($settings['show_form']))
            <form method="POST" action="{{ route('shop.review', $product->slug) }}" class="cb-form cb-reviews__form">
                @csrf
                <p class="cb-reviews__form-title">Write a review</p>

                @guest
                    <label class="cb-form__field">Your name
                        <input type="text" name="author_name" required>
                    </label>
                @endguest

                <label class="cb-form__field">Rating
                    <select name="rating" required>
                        @for ($i = 5; $i >= 1; $i--)
                            <option value="{{ $i }}">{{ str_repeat('★', $i) }} — {{ $i }} out of 5</option>
                        @endfor
                    </select>
                </label>

                <label class="cb-form__field">Headline (optional)
                    <input type="text" name="title">
                </label>

                <label class="cb-form__field">Your review
                    <textarea name="body" rows="4"></textarea>
                </label>

                <div class="cb-form__actions">
                    <button class="cb-button">Submit review</button>
                </div>
                <p class="cb-review__meta">Reviews are published after moderation.</p>
            </form>
        @endif
    </div>
</section>
