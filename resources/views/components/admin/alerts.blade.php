{{-- Flash messages. One partial so every admin screen reports the same way. --}}
@php
    $alerts = [
        'status'  => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'Success'],
        'warning' => ['bg-amber-50 border-amber-200 text-amber-800', 'Heads up'],
        'error'   => ['bg-rose-50 border-rose-200 text-rose-800', 'Problem'],
    ];
@endphp

@foreach ($alerts as $key => [$classes, $label])
    @if (session($key))
        <div x-data="{ show: true }" x-show="show" class="mb-4 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm {{ $classes }}">
            <span class="font-semibold">{{ $label }}:</span>
            <span class="flex-1">{{ session($key) }}</span>
            <button @click="show = false" class="opacity-50 hover:opacity-100" aria-label="Dismiss">&times;</button>
        </div>
    @endif
@endforeach

@if (session('theme_warnings') && count(session('theme_warnings')))
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        <p class="font-semibold">The theme installed, but its templates are worth reviewing:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach (session('theme_warnings') as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if ($errors->any())
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <p class="font-semibold">Please correct the following:</p>
        <ul class="mt-1 list-inside list-disc">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
