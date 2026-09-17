{{-- Three coloured tiles linking to the first top-level categories. --}}
@php
    $tiles = modules()->enabled('shop')
        ? \App\Models\Category::shop()->active()->roots()->orderBy('sort_order')->limit(3)->get()
        : collect();
    $kicker = $settings['kicker'] ?? 'Collection';
    $linkLabel = $settings['link_label'] ?? 'Shop now';
@endphp

@if ($tiles->count() >= 3)
    <section class="sf-section">
        <div class="sf-wrap">
            <div class="sf-tiles">
                @foreach ($tiles as $tile)
                    <a href="{{ $tile->url() }}" class="sf-tile sf-tile--{{ ['a', 'b', 'c'][$loop->index] }} {{ $tile->image ? 'sf-tile--shade' : '' }}">
                        @if ($tile->image)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($tile->image) }}"
                                 alt="" loading="lazy">
                        @endif
                        <span class="sf-tile__body">
                            @if (filled($kicker))
                                <span class="sf-tile__kicker">{{ $kicker }}</span>
                            @endif
                            <span class="sf-tile__title">{{ $tile->name }}</span>
                            <span class="sf-tile__link">
                                {{ $linkLabel }}
                                @include('theme::partials.icon', ['name' => 'arrow-up-right'])
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@elseif ($editing)
    <div class="cb-placeholder">Collection tiles — needs at least three top-level shop categories.</div>
@endif
