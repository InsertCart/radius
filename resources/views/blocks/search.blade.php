<form class="cb-search" method="GET" action="{{ $action }}" role="search">
    <input type="search"
           name="q"
           value="{{ request('q') }}"
           placeholder="{{ $settings['placeholder'] ?? 'Search...' }}"
           aria-label="Search">

    @if (! empty($settings['show_button']))
        <button type="submit" class="cb-button cb-button--md">
            {{ $settings['button_text'] ?? 'Search' }}
        </button>
    @endif
</form>
