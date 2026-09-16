{{-- A titled panel shared by the description and details widgets. Uses
     <details> so collapsing works with no JavaScript. $html is trusted: it is
     built by the calling view from escaped or sanitised values. --}}
@if ($display === 'plain')
    <section class="cb-pinfo">
        @if (filled($heading))
            <h2 class="cb-pinfo__title">{{ $heading }}</h2>
        @endif
        <div class="cb-pinfo__body">{!! $html !!}</div>
    </section>
@else
    <details class="cb-pinfo cb-pinfo--collapsible" @if ($display === 'open') open @endif>
        <summary class="cb-pinfo__title">{{ filled($heading) ? $heading : 'Details' }}</summary>
        <div class="cb-pinfo__body">{!! $html !!}</div>
    </details>
@endif
