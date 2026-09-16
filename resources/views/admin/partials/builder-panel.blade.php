{{-- The "Edit with builder" card shown on the page, post and product forms.
     Expects $model and $builderType. --}}
@php $usesBuilder = $model->exists && $model->usesBuilder(); @endphp

<x-admin.card title="Visual builder">
    @if (! $model->exists)
        <p class="text-sm text-slate-500">
            Save this first, then you can design it visually.
        </p>
    @else
        <p class="text-sm text-slate-600">
            @if ($usesBuilder)
                This {{ $builderType }} is built visually. The content field above is kept as a
                fallback and is not shown to visitors.
            @else
                Design this {{ $builderType }} by dragging blocks around instead of writing HTML.
            @endif
        </p>

        <div class="mt-4 space-y-2">
            <a href="{{ route('admin.builder.edit', ['type' => $builderType, 'id' => $model->id]) }}"
               class="block w-full rounded-lg bg-slate-900 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-slate-800">
                {{ $usesBuilder ? 'Edit with builder' : 'Open the builder' }}
            </a>

            @if ($usesBuilder)
                <form method="POST" action="{{ route('admin.builder.toggle', ['type' => $builderType, 'id' => $model->id]) }}"
                      onsubmit="return confirm('Switch back to the classic editor? Your layout is kept and can be turned on again at any time.')">
                    @csrf @method('PATCH')
                    <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-xs font-medium hover:bg-slate-50">
                        Use the classic editor instead
                    </button>
                </form>
            @endif
        </div>

        @if ($builderType === 'product' && Route::has('admin.builder.region'))
            {{-- This builder only designs the description. Where the price, buttons
                 and badges go is set once for every product, in the template. --}}
            <p class="mt-4 border-t border-slate-100 pt-3 text-xs text-slate-500">
                This designs the description only. To arrange the images, price, buy buttons
                and badges for every product, edit the
                <a href="{{ route('admin.builder.region', 'product') }}" class="font-medium text-indigo-600 hover:underline">product page template</a>.
            </p>
        @endif
    @endif
</x-admin.card>
