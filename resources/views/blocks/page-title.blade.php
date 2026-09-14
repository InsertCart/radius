@php
    $tag = in_array($settings['tag'] ?? 'h1', ['h1','h2','h3','div'], true) ? $settings['tag'] : 'h1';
@endphp

@if (! empty($settings['show_breadcrumbs']))
    <nav class="cb-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ url('/') }}">Home</a>
        <span aria-hidden="true">/</span>
        <span>{{ $title }}</span>
    </nav>
@endif

<{{ $tag }} class="cb-page-title">{{ $title }}</{{ $tag }}>
