@props(['label' => null, 'name' => null, 'help' => null, 'required' => false])

<div {{ $attributes->only('class')->merge(['class' => 'space-y-1.5']) }}>
    @if ($label)
        <label @if($name) for="{{ $name }}" @endif class="block text-sm font-medium text-slate-700">
            {{ $label }}
            @if ($required)<span class="text-rose-500">*</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($help)
        <p class="text-xs text-slate-500">{{ $help }}</p>
    @endif

    @if ($name && $errors->has($name))
        <p class="text-xs text-rose-600">{{ $errors->first($name) }}</p>
    @endif
</div>
