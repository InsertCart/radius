@props([
    'name',
    'options' => [],
    'value' => [],
    'placeholder' => 'Nothing selected',
    'searchPlaceholder' => 'Search…',
])

@php
    // The browser posts strings, so the current values are compared as strings.
    $selected = collect(old($name, $value ?: []))
        ->map(fn ($v) => (string) $v)
        ->values()
        ->all();

    $list = collect($options)
        ->map(fn ($label, $key) => ['value' => (string) $key, 'label' => (string) $label])
        ->values()
        ->all();
@endphp

<div x-data="multiSelect(@js($list), @js($selected))"
     @click.outside="open = false"
     @keydown.escape.window="open = false"
     class="relative">

    {{-- Only the ticked boxes are posted. Nothing ticked posts no input at
         all, which the settings controller reads as an empty list. --}}
    <template x-for="value in selected" :key="value">
        <input type="hidden" name="{{ $name }}[]" :value="value">
    </template>

    <button type="button" @click="open = ! open" :aria-expanded="open" aria-haspopup="listbox"
            class="flex w-full items-center justify-between gap-2 rounded-lg border border-slate-300 bg-white px-3 py-2 text-left text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
        <span class="truncate" :class="selected.length ? 'text-slate-900' : 'text-slate-400'"
              x-text="summary ?? @js($placeholder)"></span>
        <svg class="h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity.duration.100ms
         class="absolute z-30 mt-1 w-full rounded-xl border border-slate-200 bg-white shadow-lg">

        <div class="border-b border-slate-100 p-2">
            <input type="search" x-model="search" placeholder="{{ $searchPlaceholder }}"
                   @keydown.enter.prevent
                   class="w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500">
        </div>

        <div class="max-h-64 overflow-y-auto p-1" role="listbox" aria-multiselectable="true">
            <template x-for="option in filtered" :key="option.value">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50">
                    <input type="checkbox" class="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                           :checked="isSelected(option.value)" @change="toggle(option.value)">
                    <span class="text-slate-700" x-text="option.label"></span>
                    <span class="ml-auto font-mono text-xs text-slate-400" x-text="option.value"></span>
                </label>
            </template>

            <p x-show="! filtered.length" class="px-2 py-3 text-sm text-slate-400">Nothing matches that search.</p>
        </div>

        <div class="flex items-center justify-between gap-2 border-t border-slate-100 px-2 py-1.5 text-xs">
            <button type="button" @click="selectVisible()" class="rounded px-2 py-1 font-medium text-indigo-600 hover:bg-indigo-50">
                <span x-text="search.trim() ? 'Select these' : 'Select all'"></span>
            </button>
            <span class="text-slate-400" x-text="`${selected.length} selected`"></span>
            <button type="button" @click="clear()" class="rounded px-2 py-1 font-medium text-slate-500 hover:bg-slate-100">
                Clear
            </button>
        </div>
    </div>
</div>
