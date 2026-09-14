{{-- The per-row "design" link on a content listing.

     Expects $model and $builderType. Shows whether that row is already built
     visually, so an editor can tell at a glance which pages are which. --}}
@php $built = $model->usesBuilder(); @endphp

<a href="{{ route('admin.builder.edit', ['type' => $builderType, 'id' => $model->id]) }}"
   @class([
       'inline-flex items-center gap-1 text-xs font-medium',
       'text-indigo-600 hover:underline' => $built,
       'text-slate-500 hover:text-indigo-600' => ! $built,
   ])
   title="{{ $built ? 'Edit this design in the builder' : 'Design this with the builder' }}">
    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
    </svg>
    {{ $built ? 'Design' : 'Build' }}
</a>
