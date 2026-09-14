{{-- One row in the menu editor. Included recursively for child items. --}}
<div x-data="{ editing: false }" class="flex flex-wrap items-center justify-between gap-3">
    <div class="min-w-0">
        <p class="text-sm font-medium text-slate-900">
            {{ $item->label }}
            @unless ($item->is_active)
                <x-admin.badge color="gray" class="ml-1">Hidden</x-admin.badge>
            @endunless
            @if ($item->requires_module)
                <x-admin.badge color="blue" class="ml-1">{{ modules()->name($item->requires_module) }}</x-admin.badge>
            @endif
        </p>
        <p class="truncate text-xs text-slate-400">{{ $item->resolveUrl() }}</p>
    </div>

    <div class="flex items-center gap-2">
        <button type="button" @click="editing = ! editing" class="text-xs text-slate-500 hover:text-indigo-600">Edit</button>
        <form method="POST" action="{{ route('admin.menus.items.destroy', $item) }}"
              onsubmit="return confirm('Remove this item?')">
            @csrf @method('DELETE')
            <button class="text-xs text-rose-600 hover:underline">Remove</button>
        </form>
    </div>

    <form x-show="editing" x-cloak method="POST" action="{{ route('admin.menus.items.update', $item) }}"
          class="w-full space-y-3 rounded-xl bg-slate-50 p-3">
        @csrf @method('PATCH')
        <input type="hidden" name="type" value="{{ $item->type }}">
        <input type="hidden" name="reference_id" value="{{ $item->reference_id }}">

        <div class="grid gap-3 sm:grid-cols-2">
            <input type="text" name="label" value="{{ $item->label }}" required
                   class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            <input type="text" name="url" value="{{ $item->url }}" placeholder="URL"
                   class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
            <select name="target" class="rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                <option value="_self" @selected($item->target === '_self')>Same tab</option>
                <option value="_blank" @selected($item->target === '_blank')>New tab</option>
            </select>
            <label class="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked($item->is_active)
                       class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                Visible
            </label>
        </div>

        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white">Save item</button>
    </form>
</div>
