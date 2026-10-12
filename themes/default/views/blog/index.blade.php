@extends('theme::layout')

@section('content')
    {{-- The markup is a builder section, so the "Blog index" page opens in the
         editor as exactly what is shown here. The controller's posts are
         handed over so the section does not have to find them again. --}}
    @php $listing = ['posts' => $posts, 'categories' => $categories]; @endphp

    @region('blog_index', $listing)
        @include('theme::sections.blog-list', [
            'settings' => [],
            'context' => $listing,
            'editing' => false,
            'model' => null,
        ])
    @endregion
@endsection
