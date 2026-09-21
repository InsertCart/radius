@extends('theme::layout')

@section('content')
    @region('home')
        @region('hero')
            {!! theme_section('hero') !!}
        @endregion

        @php
            $defaultSections = ['features'];
            if (modules()->enabled('shop')) {
                $defaultSections[] = 'categories';
                $defaultSections[] = 'rail';
            }
            if (modules()->enabled('blog')) {
                $defaultSections[] = 'journal';
            }
            if (modules()->enabled('newsletter')) {
                $defaultSections[] = 'newsletter';
            }
        @endphp

        @foreach ($defaultSections as $sectionName)
            {!! theme_section($sectionName) !!}
        @endforeach

        @region('home_bottom')@endregion
    @endregion

    @if ($featuredPosts->isEmpty() && $featuredProducts->isEmpty())
        <section style="padding: 90px 24px; text-align: center;">
            <div class="zn-wrap zn-wrap--narrow">
                <span class="zn-badge zn-badge--accent" style="margin-bottom: 16px;">WELCOME TO ZENITH</span>
                <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 12px;">Your boutique is ready</h2>
                <p style="color: var(--zn-muted); font-size: 16px; margin: 0 auto 28px; max-width: 500px;">
                    Welcome to your new store and editorial platform. Sign in to the admin panel to add products, create journal stories, and customize your visual layouts.
                </p>
                @staff
                    <a href="{{ route('admin.dashboard') }}" class="zn-btn zn-btn--primary zn-btn--lg">
                        Open Admin Dashboard
                        @include('theme::partials.icon', ['name' => 'arrow-right'])
                    </a>
                @endstaff
            </div>
        </section>
    @endif
@endsection
