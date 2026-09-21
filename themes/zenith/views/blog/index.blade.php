@extends('theme::layout')

@section('content')
    <div class="zn-wrap" style="padding-top: 24px; padding-bottom: 80px;">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Journal & Essays</b>
        </nav>

        <header style="max-width: 760px; margin-bottom: 44px;">
            <div class="zn-section-head__kicker">EDITORIAL DISPATCHES</div>
            <h1 style="font-size: clamp(32px, 4.5vw, 48px); font-weight: 800; margin-bottom: 12px; letter-spacing: -0.03em;">
                The Zenith Journal
            </h1>
            <p style="color: var(--zn-muted); font-size: 17px; margin: 0; line-height: 1.6;">
                Essays on craftsmanship, design philosophies, material origins, and modern culture.
            </p>
        </header>

        @if ($posts->isEmpty())
            <div style="text-align: center; padding: 70px 20px; background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl);">
                <h3 style="font-size: 20px; font-weight: 700; margin-bottom: 8px;">No journal entries found</h3>
                <p style="color: var(--zn-muted); font-size: 14px; margin-bottom: 20px;">Check back soon for upcoming essays and stories.</p>
                <a href="{{ url('/') }}" class="zn-btn zn-btn--primary zn-btn--sm">Return to Frontpage</a>
            </div>
        @else
            {{-- Featured Story Hero Card --}}
            @php $featuredPost = $posts->first(); @endphp
            @if ($featuredPost && !request('q') && !request('page'))
                <article style="position: relative; border-radius: var(--zn-radius-xl); overflow: hidden; background: var(--zn-surface); border: 1px solid var(--zn-border); margin-bottom: 44px; display: grid; grid-template-columns: 1.2fr 1fr; gap: 0;">
                    <div style="min-height: 340px; background: var(--zn-bg-alt); position: relative;">
                        @if ($featuredPost->imageUrl())
                            <img src="{{ $featuredPost->imageUrl('large') ?: $featuredPost->imageUrl() }}" alt="{{ $featuredPost->title }}"
                                 style="width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0;">
                        @endif
                    </div>

                    <div style="padding: 40px; display: flex; flex-direction: column; justify-content: center;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; font-size: 12px; color: var(--zn-muted);">
                            <span class="zn-badge zn-badge--accent">FEATURED ESSAY</span>
                            @if ($featuredPost->category)
                                <span>&bull;</span>
                                <span style="font-weight: 700; text-transform: uppercase;">{{ $featuredPost->category->name }}</span>
                            @endif
                            <span>&bull;</span>
                            <span>{{ format_date($featuredPost->published_at) }}</span>
                        </div>

                        <h2 style="font-size: clamp(24px, 2.5vw, 32px); font-weight: 800; margin-bottom: 14px; line-height: 1.25;">
                            <a href="{{ $featuredPost->url() }}">{{ $featuredPost->title }}</a>
                        </h2>

                        <p style="color: var(--zn-muted); font-size: 15px; line-height: 1.6; margin-bottom: 24px;">
                            {{ $featuredPost->summary() }}
                        </p>

                        <div>
                            <a href="{{ $featuredPost->url() }}" class="zn-btn zn-btn--primary">
                                Read Complete Essay
                                @include('theme::partials.icon', ['name' => 'arrow-right'])
                            </a>
                        </div>
                    </div>
                </article>
            @endif

            {{-- Grid of Remaining Posts --}}
            <div class="zn-grid--journal">
                @foreach ($posts->skip((!request('q') && !request('page')) ? 1 : 0) as $post)
                    @include('theme::partials.post-card', ['post' => $post])
                @endforeach
            </div>

            {{ $posts->links('theme::partials.pagination') }}
        @endif
    </div>
@endsection
