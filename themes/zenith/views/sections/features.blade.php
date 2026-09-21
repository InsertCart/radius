@php
    $kicker = trim((string) ($settings['kicker'] ?? 'THE ZENITH STANDARD'));
    $title = trim((string) ($settings['title'] ?? 'Designed without compromise'));
@endphp

<section class="zn-features">
    <div class="zn-wrap">
        <div style="text-align: center; max-width: 680px; margin: 0 auto 44px;">
            @if ($kicker !== '')
                <div class="zn-section-head__kicker">{{ $kicker }}</div>
            @endif
            <h2 class="zn-section-head__title">{{ $title }}</h2>
            <p class="zn-section-head__subtitle" style="margin: 0 auto;">
                Every creation embodies a philosophy of enduring excellence, uncompromising material choices, and thoughtful engineering.
            </p>
        </div>

        <div class="zn-features__grid">
            <div class="zn-feature-card">
                <div class="zn-feature-icon">
                    @include('theme::partials.icon', ['name' => 'shield'])
                </div>
                <h3 class="zn-feature-title">Master Craftsmanship</h3>
                <p class="zn-feature-desc">Engineered in limited batches with obsessive quality control and a lifetime durability guarantee.</p>
            </div>

            <div class="zn-feature-card">
                <div class="zn-feature-icon">
                    @include('theme::partials.icon', ['name' => 'leaf'])
                </div>
                <h3 class="zn-feature-title">Responsible Sourcing</h3>
                <p class="zn-feature-desc">100% ethically certified supply chain adhering to strict circular economy principles.</p>
            </div>

            <div class="zn-feature-card">
                <div class="zn-feature-icon">
                    @include('theme::partials.icon', ['name' => 'truck'])
                </div>
                <h3 class="zn-feature-title">Express Climate-Neutral</h3>
                <p class="zn-feature-desc">Complimentary priority delivery on qualifying orders, offset with verified carbon credits.</p>
            </div>

            <div class="zn-feature-card">
                <div class="zn-feature-icon">
                    @include('theme::partials.icon', ['name' => 'headset'])
                </div>
                <h3 class="zn-feature-title">Private Concierge</h3>
                <p class="zn-feature-desc">Direct access to product specialists for sizing, custom requests, and post-purchase care.</p>
            </div>
        </div>
    </div>
</section>
