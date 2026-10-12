@extends('theme::layout')

@section('content')
    {{-- Every block below is a builder section (see "sections" in theme.json).
         Designing the "Homepage (whole page)" region replaces all of it; the
         "Homepage hero" region replaces only the banner.

         The sections are included directly rather than through
         theme_section(), so a theme that borrows this file without declaring
         the sections itself still gets a complete homepage. --}}
    @php $section = ['settings' => [], 'context' => [], 'editing' => false, 'model' => null]; @endphp

    @region('home')
        @region('hero')
            @include('theme::sections.hero', $section)
        @endregion

        @module('shop')
            @include('theme::sections.featured-products', $section)
        @endmodule

        @module('blog')
            @include('theme::sections.latest-posts', $section)
        @endmodule

        @region('home_bottom')@endregion
    @endregion

    @if ($featuredPosts->isEmpty() && $featuredProducts->isEmpty())
        <section class="mx-auto max-w-2xl px-4 py-20 text-center">
            <h2 class="text-xl font-semibold text-slate-900">Your site is ready</h2>
            <p class="mt-3 text-slate-600">
                Nothing has been published yet. Sign in to the admin panel to add pages, write a
                post, or list your first product.
            </p>
            @staff
                <a href="{{ route('admin.dashboard') }}" class="mt-6 inline-block rounded-lg px-6 py-3 text-sm font-semibold text-white btn-brand">
                    Open the admin panel
                </a>
            @endstaff
        </section>
    @endif
@endsection
