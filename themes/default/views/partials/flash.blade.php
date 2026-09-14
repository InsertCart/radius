@php
    $flashes = ['status' => 'emerald', 'warning' => 'amber', 'error' => 'rose'];
@endphp

@foreach ($flashes as $key => $color)
    @if (session($key))
        <div x-data="{ show: true }" x-show="show"
             class="mx-auto mt-4 flex max-w-6xl items-start gap-3 rounded-xl border border-{{ $color }}-200 bg-{{ $color }}-50 px-4 py-3 text-sm text-{{ $color }}-800">
            <span class="flex-1">{{ session($key) }}</span>
            <button @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss">&times;</button>
        </div>
    @endif
@endforeach

@if ($errors->any())
    <div class="mx-auto mt-4 max-w-6xl rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
        <ul class="list-inside list-disc space-y-0.5">
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    </div>
@endif
