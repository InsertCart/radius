@props([
    'name',
    'value' => null,
    'label' => 'Content',
    'minHeight' => '320px',
])

{{-- The textarea stays in the DOM and keeps carrying the value, so the form
     posts exactly as it did before. The editor mounts beside it and syncs
     into it, which means the builder, validation and old() all keep working
     with no changes elsewhere. --}}
<textarea name="{{ $name }}"
          id="{{ $name }}"
          data-richtext
          data-label="{{ $label }}"
          data-min-height="{{ $minHeight }}"
          {{ $attributes }}>{{ old($name, $value) }}</textarea>

@error($name)
    <p class="mt-1 text-xs text-rose-600">{{ $message }}</p>
@enderror
