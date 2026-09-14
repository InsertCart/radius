@props(['name' => null])

{{-- Inline SVG from the built-in library. The name is looked up in a fixed
     table, so a stored setting cannot inject markup. --}}
@if ($name && \App\Cms\Builder\IconLibrary::has($name))
    {!! \App\Cms\Builder\IconLibrary::svg($name, $attributes->getAttributes()) !!}
@endif
