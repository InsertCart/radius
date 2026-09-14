@props(['name', 'label', 'checked' => false, 'help' => null])

{{-- The hidden input means an unchecked box still submits a value, so a
     boolean setting can be turned off rather than merely omitted. --}}
<label class="flex cursor-pointer items-start gap-3">
    <input type="hidden" name="{{ $name }}" value="0">
    <input type="checkbox"
           name="{{ $name }}"
           id="{{ $name }}"
           value="1"
           @checked(old($name, $checked))
           {{ $attributes->merge(['class' => 'mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500']) }}>
    <span>
        <span class="block text-sm font-medium text-slate-700">{{ $label }}</span>
        @if ($help)
            <span class="block text-xs text-slate-500">{{ $help }}</span>
        @endif
    </span>
</label>
