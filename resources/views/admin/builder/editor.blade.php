<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} &middot; Builder</title>

    @php
        // Everything the editor boots from. Passed as data rather than baked
        // into the bundle so the same script works under any install path.
        $boot = [
            'config' => $config,
            'tree' => $tree,
            'widgets' => $widgets,
            'schemas' => $schemas,
            'structure' => $structure,
            'icons' => $icons,
            'tokens' => $tokens,
            'fonts' => $fonts,
            'area' => $area,
            'meta' => [
                'title' => $title,
                'subtitle' => $subtitle,
                'previewUrl' => $previewUrl,
                'backUrl' => $backUrl,
                'viewUrl' => $viewUrl,
            ],
        ];
    @endphp
    <script>window.CB_BOOT = @json($boot);</script>

    @vite(['resources/css/builder.css', 'resources/js/builder/index.js'])
</head>
<body class="cb-editor">

<div class="cb-editor__layout" id="cb-app">

    {{-- Left panel: widget list, settings, or the navigator ------------- --}}
    <aside class="cb-panel" id="cb-panel">
        <header class="cb-panel__head">
            <div class="cb-panel__brand">
                <a href="{{ $backUrl }}" class="cb-panel__back" title="Back to the admin panel" aria-label="Back">
                    <x-cb-icon name="chevron-left" />
                </a>
                <div class="cb-panel__titles">
                    <strong id="cb-panel-title">{{ $title }}</strong>
                    <span>{{ $subtitle }}</span>
                </div>
            </div>

            <button type="button" class="cb-icon-btn" id="cb-panel-widgets" title="All widgets" aria-label="All widgets">
                <x-cb-icon name="grid" />
            </button>
        </header>

        {{-- Widget picker --}}
        <div class="cb-panel__body" id="cb-view-widgets">
            @if (! empty($region))
                {{-- Explains why a region opens empty while the live site
                     clearly has a header, and offers a way in. Hidden by the
                     editor as soon as anything is added. --}}
                <div class="cb-region-note" id="cb-region-note" @if (! empty($tree)) hidden @endif>
                    <p class="cb-region-note__title">Your theme's {{ $region }} is in use</p>
                    <p>
                        Nothing has been built here, so the live site is still showing the
                        {{ $region }} that came with your theme. Anything you build replaces it;
                        empty it again and the theme's version comes back.
                    </p>

                    @if (! empty($starters))
                        <p class="cb-region-note__label">Start from your theme</p>
                        <div class="cb-starters">
                            @foreach ($starters as $starter)
                                <button type="button"
                                        class="cb-starter"
                                        data-starter="{{ $starter['key'] }}"
                                        data-region="{{ $region }}">
                                    <strong>{{ $starter['label'] }}</strong>
                                    <span>{{ $starter['hint'] }}</span>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <div class="cb-search-box">
                <x-cb-icon name="search" />
                <input type="search" id="cb-widget-search" placeholder="Search widgets" aria-label="Search widgets">
            </div>
            <div id="cb-widget-list" class="cb-widget-list"></div>
        </div>

        {{-- Settings for the selected element --}}
        <div class="cb-panel__body" id="cb-view-settings" hidden>
            <div class="cb-settings-head">
                <button type="button" class="cb-icon-btn" id="cb-settings-back" aria-label="Back to widgets">
                    <x-cb-icon name="chevron-left" />
                </button>
                <span id="cb-settings-title">Settings</span>
                <div class="cb-settings-head__actions">
                    <button type="button" class="cb-icon-btn" id="cb-duplicate" title="Duplicate" aria-label="Duplicate">
                        <x-cb-icon name="clipboard" />
                    </button>
                    <button type="button" class="cb-icon-btn cb-icon-btn--danger" id="cb-delete" title="Delete" aria-label="Delete">
                        <x-cb-icon name="close" />
                    </button>
                </div>
            </div>

            <nav class="cb-tabs-nav" id="cb-tabs" role="tablist">
                <button type="button" role="tab" data-tab="content" aria-selected="true">Content</button>
                <button type="button" role="tab" data-tab="style" aria-selected="false">Style</button>
                <button type="button" role="tab" data-tab="advanced" aria-selected="false">Advanced</button>
            </nav>

            <div class="cb-controls" id="cb-controls"></div>
        </div>

        {{-- Structure tree --}}
        <div class="cb-panel__body" id="cb-view-navigator" hidden>
            <div class="cb-settings-head">
                <button type="button" class="cb-icon-btn" id="cb-navigator-back" aria-label="Back">
                    <x-cb-icon name="chevron-left" />
                </button>
                <span>Structure</span>
            </div>
            <div id="cb-navigator" class="cb-navigator"></div>
        </div>

        <footer class="cb-panel__foot">
            <button type="button" class="cb-icon-btn" id="cb-open-navigator" title="Structure" aria-label="Structure">
                <x-cb-icon name="tabs" />
            </button>
            <button type="button" class="cb-icon-btn" id="cb-undo" title="Undo (Ctrl+Z)" aria-label="Undo" disabled>
                <x-cb-icon name="arrow-left" />
            </button>
            <button type="button" class="cb-icon-btn" id="cb-redo" title="Redo (Ctrl+Shift+Z)" aria-label="Redo" disabled>
                <x-cb-icon name="arrow-right" />
            </button>
            <span class="cb-save-state" id="cb-save-state"></span>
            <button type="button" class="cb-btn cb-btn--primary" id="cb-publish">Publish</button>
        </footer>
    </aside>

    {{-- Canvas ------------------------------------------------------------ --}}
    <main class="cb-stage">
        <div class="cb-toolbar">
            <div class="cb-toolbar__group" role="group" aria-label="Preview size">
                @foreach (config('builder.breakpoints') as $key => $device)
                    <button type="button"
                            class="cb-icon-btn @if ($key === 'desktop') is-active @endif"
                            data-device="{{ $key }}"
                            title="{{ $device['label'] }}"
                            aria-label="{{ $device['label'] }}">
                        <x-cb-icon :name="$device['icon']" />
                    </button>
                @endforeach
            </div>

            <div class="cb-toolbar__spacer"></div>

            <div class="cb-toolbar__group">
                @if ($viewUrl)
                    <a href="{{ $viewUrl }}" target="_blank" rel="noopener" class="cb-btn cb-btn--ghost">View live</a>
                @endif

                @if ($canRestore && $restoreUrl)
                    {{-- The way back out when a redesign has gone wrong. The
                         discarded version is snapshotted first, so this is
                         itself reversible from History. --}}
                    <form method="POST" action="{{ $restoreUrl }}"
                          onsubmit="return confirm('Discard this design and go back to the default?\n\nYour current version is saved to History first, so you can bring it back.')">
                        @csrf
                        <button type="submit" class="cb-btn cb-btn--ghost cb-btn--warn">Restore default</button>
                    </form>
                @endif

                <a href="{{ route('admin.builder.revisions', $layout) }}" class="cb-btn cb-btn--ghost">History</a>
                <a href="{{ $backUrl }}" class="cb-btn cb-btn--ghost">Exit</a>
            </div>
        </div>

        @if (! empty($area['requires_widget']))
            {{-- A cart page without a cart, or a checkout without a
                 checkout, would leave customers unable to buy. The editor
                 watches for it rather than letting it be published quietly. --}}
            <div class="cb-guard" id="cb-guard" data-requires="{{ $area['requires_widget'] }}" hidden>
                @php
                    [$requiredName, $requiredReason, $requiredGroup] = match ($area['requires_widget']) {
                        'checkout' => ['Checkout', 'pay', 'Shop'],
                        'product-add-to-cart' => ['Add to cart', 'buy this product', 'Product page'],
                        default => ['Cart', 'see their basket', 'Shop'],
                    };
                @endphp
                <strong>This page needs the {{ $requiredName }} widget.</strong>
                <span>Without it your customers cannot {{ $requiredReason }}. Drag it in from the {{ $requiredGroup }} group, or use Restore default.</span>
            </div>
        @endif

        <div class="cb-canvas-wrap" id="cb-canvas-wrap">
            <iframe id="cb-canvas"
                    class="cb-canvas"
                    src="{{ $previewUrl }}"
                    title="Page preview"></iframe>

            {{-- Overlay chrome drawn above the iframe: selection outline,
                 element toolbar and the drop indicator. --}}
            <div class="cb-overlay-layer" id="cb-overlay" aria-hidden="true">
                <div class="cb-outline" id="cb-outline" hidden>
                    <span class="cb-outline__label" id="cb-outline-label"></span>
                </div>
                <div class="cb-elem-toolbar" id="cb-elem-toolbar" hidden>
                    <button type="button" data-action="drag" title="Drag" aria-label="Drag"><x-cb-icon name="spacer" /></button>
                    <button type="button" data-action="duplicate" title="Duplicate" aria-label="Duplicate"><x-cb-icon name="clipboard" /></button>
                    <button type="button" data-action="delete" title="Delete" aria-label="Delete"><x-cb-icon name="close" /></button>
                </div>
                <div class="cb-drop-line" id="cb-drop-line" hidden></div>
            </div>

            <div class="cb-loading" id="cb-loading">
                <span class="cb-spinner"></span>
                <p>Loading the canvas…</p>
            </div>
        </div>
    </main>
</div>

{{-- Drag ghost that follows the pointer when dragging a widget in --}}
<div class="cb-drag-ghost" id="cb-drag-ghost" hidden></div>

{{-- Media picker, opened by image and gallery controls --}}
<div class="cb-modal" id="cb-media-modal" hidden>
    <div class="cb-modal__backdrop" data-close></div>
    <div class="cb-modal__panel">
        <header class="cb-modal__head">
            <strong>Choose an image</strong>
            <div class="cb-modal__head-actions">
                <label class="cb-btn cb-btn--ghost">
                    Upload
                    <input type="file" id="cb-media-upload" accept="image/*" multiple hidden>
                </label>
                <button type="button" class="cb-icon-btn" data-close aria-label="Close"><x-cb-icon name="close" /></button>
            </div>
        </header>
        <div class="cb-modal__body" id="cb-media-grid"></div>
        <footer class="cb-modal__foot">
            <button type="button" class="cb-btn cb-btn--ghost" data-close>Cancel</button>
            <button type="button" class="cb-btn cb-btn--primary" id="cb-media-choose">Use selected</button>
        </footer>
    </div>
</div>

{{-- Icon picker --}}
<div class="cb-modal" id="cb-icon-modal" hidden>
    <div class="cb-modal__backdrop" data-close></div>
    <div class="cb-modal__panel cb-modal__panel--narrow">
        <header class="cb-modal__head">
            <strong>Choose an icon</strong>
            <button type="button" class="cb-icon-btn" data-close aria-label="Close"><x-cb-icon name="close" /></button>
        </header>
        <div class="cb-modal__body">
            <div class="cb-search-box">
                <x-cb-icon name="search" />
                <input type="search" id="cb-icon-search" placeholder="Search icons" aria-label="Search icons">
            </div>
            <div id="cb-icon-grid" class="cb-icon-grid"></div>
        </div>
    </div>
</div>

{{-- Every icon the editor can draw, as a hidden sprite the JS clones from.
     Keeps icon markup in PHP rather than duplicating the set in JavaScript. --}}
<div id="cb-icon-sprite" hidden>
    @foreach (\App\Cms\Builder\IconLibrary::names() as $iconName)
        <template data-icon="{{ $iconName }}">{!! \App\Cms\Builder\IconLibrary::svg($iconName) !!}</template>
    @endforeach
</div>

</body>
</html>
