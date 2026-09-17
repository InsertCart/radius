{{-- Round category shortcuts. --}}
@php
    $departments = modules()->enabled('shop')
        ? \App\Models\Category::shop()->active()->roots()->orderBy('sort_order')
            ->limit(max(1, min(20, (int) ($settings['count'] ?? 10))))->get()
        : collect();
@endphp

@if ($departments->isNotEmpty())
    <section class="sf-section {{ ! empty($settings['soft']) ? 'sf-section--soft' : '' }}">
        <div class="sf-wrap">
            <div class="sf-section__head">
                <div>
                    @if (filled($settings['title'] ?? null))
                        <h2 class="sf-section__title">{{ $settings['title'] }}</h2>
                    @endif
                    @if (filled($settings['subtitle'] ?? null))
                        <p class="sf-section__sub">{{ $settings['subtitle'] }}</p>
                    @endif
                </div>
            </div>

            <div class="sf-circles">
                @foreach ($departments as $category)
                    <a href="{{ $category->url() }}" class="sf-circle">
                        <span class="sf-circle__ring">
                            @if ($category->image)
                                <img src="{{ \Illuminate\Support\Facades\Storage::disk(config('cms.media.disk'))->url($category->image) }}"
                                     alt="" loading="lazy">
                            @else
                                {{ \Illuminate\Support\Str::substr($category->name, 0, 1) }}
                            @endif
                        </span>
                        <span class="sf-circle__label">{{ $category->name }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>
@elseif ($editing)
    <div class="cb-placeholder">Shop by department — add some shop categories first.</div>
@endif
