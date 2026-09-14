@props(['name', 'options' => [], 'value' => null, 'placeholder' => null])

<select name="{{ $name }}"
        id="{{ $name }}"
        {{ $attributes->merge([
            'class' => 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500',
        ]) }}>
    @if ($placeholder)
        <option value="">{{ $placeholder }}</option>
    @endif
    @foreach ($options as $key => $label)
        <option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $label }}</option>
    @endforeach
</select>
