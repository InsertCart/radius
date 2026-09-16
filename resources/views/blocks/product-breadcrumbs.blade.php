@php $category = $product->categories->first(); @endphp

<nav class="cb-breadcrumbs" aria-label="Breadcrumb">
    <a href="{{ url('/') }}">{{ $settings['home_label'] ?: 'Home' }}</a>
    <span aria-hidden="true">/</span>
    <a href="{{ safe_route('shop.index', [], '#') }}">{{ $settings['shop_label'] ?: 'Shop' }}</a>
    @if ($category)
        <span aria-hidden="true">/</span>
        <a href="{{ $category->url() }}">{{ $category->name }}</a>
    @endif
    <span aria-hidden="true">/</span>
    <span aria-current="page">{{ $product->name }}</span>
</nav>
