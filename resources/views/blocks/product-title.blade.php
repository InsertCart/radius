@php
    $tag = in_array($settings['tag'] ?? 'h1', ['h1', 'h2', 'div'], true) ? $settings['tag'] : 'h1';
    $category = $product->categories->first();
@endphp

<{{ $tag }} class="cb-ptitle">{{ $product->name }}</{{ $tag }}>

@if (! empty($settings['show_category']) && $category)
    <p class="cb-ptitle__category-wrap">
        <a class="cb-ptitle__category" href="{{ $category->url() }}">{{ $category->name }}</a>
    </p>
@endif
