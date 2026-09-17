<form class="cb-search" method="GET" action="{{ $action }}" role="search"
      data-radius-search="{{ $type }}"
      @unless ($instant) data-radius-instant="off" @endunless>
    <input type="search"
           name="q"
           value="{{ request('q') }}"
           placeholder="{{ $settings['placeholder'] ?? 'Search...' }}"
           autocomplete="off"
           aria-label="Search">

    @if (! empty($settings['show_button']))
        <button type="submit" class="cb-button cb-button--md">
            {{ $settings['button_text'] ?? 'Search' }}
        </button>
    @endif
</form>

{{-- Not while editing: the canvas re-renders widgets over AJAX, where an
     inline script would never run anyway. --}}
@unless ($editing)
    @searchScripts
@endunless
