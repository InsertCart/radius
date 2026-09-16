@if ($related->isEmpty())
    {!! $editing ? '<div class="cb-placeholder">Related products (no other products yet)</div>' : '' !!}
@else
    <section class="cb-related">
        @if (filled($settings['heading'] ?? null))
            <h2 class="cb-related__heading">{{ $settings['heading'] }}</h2>
        @endif

        @include('blocks.products', [
            'products' => $related,
            'settings' => $settings,
        ])
    </section>
@endif
