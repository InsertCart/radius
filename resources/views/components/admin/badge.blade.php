@props(['color' => 'gray'])

@php
    $palette = [
        'green'  => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'red'    => 'bg-rose-50 text-rose-700 ring-rose-600/20',
        'amber'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'blue'   => 'bg-blue-50 text-blue-700 ring-blue-600/20',
        'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
        'gray'   => 'bg-slate-50 text-slate-600 ring-slate-500/20',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($palette[$color] ?? $palette['gray'])]) }}>
    {{ $slot }}
</span>
