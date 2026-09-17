@extends('theme::layout')

@section('content')
    {{-- Every block below is a builder section (see "sections" in theme.json).
         Designing the "Homepage (whole page)" region replaces all of it; the
         "Homepage hero" region replaces only the banner. --}}
    @region('home')
        @region('hero')
            {!! theme_section('hero') !!}
        @endregion

        @module('shop')
            {!! theme_section('rail', ['count' => 8]) !!}
            {!! theme_section('departments') !!}
            {!! theme_section('category_rails') !!}
            {!! theme_section('collections') !!}
        @endmodule

        {!! theme_section('reading_desk') !!}

        @region('home_bottom')@endregion
    @endregion

    @if ($featuredPosts->isEmpty() && $featuredProducts->isEmpty())
        <section class="sf-section">
            <div class="sf-wrap sf-wrap--narrow sf-center">
                <h2 class="sf-section__title">Your storefront is ready</h2>
                <p class="sf-mt sf-muted">
                    Nothing has been published yet. Sign in to the admin panel to add your
                    first products, build a category menu and design the homepage hero.
                </p>
                @staff
                    <p class="sf-mt">
                        <a href="{{ route('admin.dashboard') }}" class="sf-btn sf-btn--primary sf-btn--lg">Open the admin panel</a>
                    </p>
                @endstaff
            </div>
        </section>
    @endif
@endsection
