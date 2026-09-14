@php
    $items = array_values(array_filter($settings['items'] ?? [], fn ($i) => filled($i['title'] ?? null)));
    $uid = $context['id'] ?? uniqid();
    $orientation = $settings['orientation'] ?? 'horizontal';
@endphp

@if (empty($items))
    {!! $block->placeholder('Add a tab', $context) !!}
@else
    <div class="cb-tabs cb-tabs--{{ $orientation }}" data-cb-tabs>
        <div class="cb-tabs__list" role="tablist" aria-orientation="{{ $orientation }}">
            @foreach ($items as $index => $item)
                <button type="button"
                        class="cb-tabs__tab"
                        role="tab"
                        id="cb-tab-{{ $uid }}-{{ $index }}"
                        aria-controls="cb-tabpanel-{{ $uid }}-{{ $index }}"
                        aria-selected="{{ $index === 0 ? 'true' : 'false' }}"
                        tabindex="{{ $index === 0 ? '0' : '-1' }}">
                    @if (filled($item['icon'] ?? null))
                        <x-cb-icon :name="$item['icon']" />
                    @endif
                    <span>{{ $item['title'] }}</span>
                </button>
            @endforeach
        </div>

        @foreach ($items as $index => $item)
            <div class="cb-tabs__panel"
                 role="tabpanel"
                 id="cb-tabpanel-{{ $uid }}-{{ $index }}"
                 aria-labelledby="cb-tab-{{ $uid }}-{{ $index }}"
                 @unless ($index === 0) hidden @endunless>
                <div class="prose-content">{!! $item['content'] ?? '' !!}</div>
            </div>
        @endforeach
    </div>
@endif
