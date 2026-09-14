@php
    $items = array_values(array_filter($settings['items'] ?? [], fn ($i) => filled($i['title'] ?? null)));
    $single = ! empty($settings['single']);
    $uid = $context['id'] ?? uniqid();
@endphp

@if (empty($items))
    {!! $block->placeholder('Add a panel', $context) !!}
@else
    <div class="cb-accordion" data-cb-accordion @if ($single) data-cb-single @endif>
        @foreach ($items as $index => $item)
            @php $panelId = 'cb-panel-'.$uid.'-'.$index; $open = ! empty($item['open']); @endphp

            <div class="cb-accordion__item">
                {{-- A real button with aria-expanded, so the accordion works
                     with a keyboard and announces its state. --}}
                <button type="button"
                        class="cb-accordion__title"
                        aria-expanded="{{ $open ? 'true' : 'false' }}"
                        aria-controls="{{ $panelId }}">
                    <span>{{ $item['title'] }}</span>
                    <span class="cb-accordion__icon" aria-hidden="true">
                        <x-cb-icon :name="$settings['icon'] ?? 'chevron-down'" />
                    </span>
                </button>

                <div class="cb-accordion__panel" id="{{ $panelId }}" @unless ($open) hidden @endunless>
                    <div class="prose-content">{!! $item['content'] ?? '' !!}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if (! empty($settings['faq_schema']))
        @php
            $faq = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => array_map(fn ($item) => [
                    '@type' => 'Question',
                    'name' => $item['title'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => trim(strip_tags((string) ($item['content'] ?? ''))),
                    ],
                ], $items),
            ];
        @endphp
        <script type="application/ld+json">{!! json_encode($faq, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endif
