@props(['model'])

@php
    /*
     | A "View" button for the header of a page/post/product editor.
     |
     | Three outcomes, because only one of them is a working link:
     |  - unsaved record, or no public route (shop/blog module off) → nothing
     |  - live            → open the public URL in a new tab
     |  - not live yet    → a disabled button saying why
     |
     | The state reads from the saved record, not from the Status select: a
     | status change is only real once the form has been submitted.
     */
    $url = $model->exists ? $model->url() : '#';
    $show = $model->exists && $url !== '#';
    $live = $show && $model->isPublished();

    // Posts can sit at "published" with a future date, which is scheduled, not live.
    $label = match ($model->status) {
        'published' => 'Scheduled',
        'archived' => 'Archived',
        default => 'Still in draft',
    };
@endphp

@if ($show)
    @if ($live)
        <a href="{{ $url }}" target="_blank" rel="noopener"
           {{ $attributes->merge(['class' => 'hidden items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 sm:inline-flex']) }}>
            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25"/>
            </svg>
            View
        </a>
    @else
        <span {{ $attributes->merge(['class' => 'hidden cursor-not-allowed items-center rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-medium text-slate-400 sm:inline-flex']) }}
              title="Publish this first to view it on the site.">
            {{ $label }}
        </span>
    @endif
@endif
