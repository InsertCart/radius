@php $html = (string) ($settings['html'] ?? ''); @endphp

@if (trim($html) === '')
    {!! $block->placeholder('Paste your HTML', $context) !!}
@else
    {{-- Deliberately unescaped: outputting raw markup is the entire purpose of
         this widget, and only administrators can place one. --}}
    <div class="cb-html">{!! $html !!}</div>
@endif
