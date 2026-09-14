@php $layout = $settings['layout'] ?? 'horizontal'; @endphp

@if ($items->isEmpty())
    {!! $block->placeholder('Choose a menu, or add items to it under Content → Menus', $context) !!}
@else
    <nav class="cb-menu-wrap" data-cb-menu aria-label="{{ $menuName ?? 'Menu' }}">
        @if (! empty($settings['mobile_toggle']))
            <button type="button" class="cb-menu__toggle" aria-expanded="false" aria-label="Toggle menu">
                <x-cb-icon name="menu" />
            </button>
        @endif

        <ul class="cb-menu cb-menu--{{ $layout }}">
            @foreach ($items as $item)
                <li @class(['cb-menu__item', 'cb-menu__item--has-children' => $item->children->isNotEmpty()])>
                    <a href="{{ $item->resolveUrl() }}" target="{{ $item->target }}"
                       @if ($item->target === '_blank') rel="noopener" @endif>
                        {{ $item->label }}
                    </a>

                    @if ($item->children->isNotEmpty())
                        <ul class="cb-menu__submenu">
                            @foreach ($item->children as $child)
                                @continue(! $child->isVisible())
                                <li>
                                    <a href="{{ $child->resolveUrl() }}" target="{{ $child->target }}"
                                       @if ($child->target === '_blank') rel="noopener" @endif>
                                        {{ $child->label }}
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ul>
    </nav>
@endif
