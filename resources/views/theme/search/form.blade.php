{{-- A search box with live results.

     The CMS's own version, used by the Search widget and the /search page. A
     theme overrides it by shipping views/search/form.blade.php. A theme that
     prefers its own markup can skip this view entirely: any GET form with a
     `q` field gets live results by adding data-radius-search="all|post|product|page"
     and putting @searchScripts after it.

     Variables, all optional:
       $type         all (default), or a type key from config/search.php
       $action       where the form submits; defaults to that type's listing
       $placeholder  input placeholder
       $label        accessible label, visually hidden
       $button       false to hide the submit button
       $buttonText   submit button label
       $instant      false to turn live results off for this form only
       $value        starting value; defaults to the current ?q=
       $class        extra classes for the <form> --}}
@php
    $type = $type ?? 'all';
    $action = $action ?? search()->formAction($type);
    $inputId = 'radius-search-q-'.\Illuminate\Support\Str::random(6);
    $toSearchPage = Route::has('search') && rtrim($action, '/') === rtrim(route('search'), '/');
@endphp

<form method="GET" action="{{ $action }}" role="search"
      class="radius-search flex w-full gap-2 {{ $class ?? '' }}"
      data-radius-search="{{ $type }}"
      @if (isset($instant) && ! $instant) data-radius-instant="off" @endif>
    @if ($type !== 'all' && $toSearchPage)
        <input type="hidden" name="type" value="{{ $type }}">
    @endif

    <label for="{{ $inputId }}" class="sr-only">{{ $label ?? __('Search') }}</label>
    <input type="search" name="q" id="{{ $inputId }}"
           value="{{ $value ?? request('q') }}"
           placeholder="{{ $placeholder ?? __('Search...') }}"
           autocomplete="off"
           class="radius-search__input min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm text-slate-900">

    @if ($button ?? true)
        <button type="submit" class="radius-search__button rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">
            {{ $buttonText ?? __('Search') }}
        </button>
    @endif
</form>

@searchScripts
