{{-- The document loaded into the editor's iframe.

     When $chrome is true this extends the active theme's layout, so the
     preview carries the real header, footer, fonts and widths - what an admin
     arranges is what a visitor will see. A region is previewed without the
     chrome, since it is a fragment rather than a page. --}}

@extends($chrome ? 'theme::layout' : 'admin.builder.bare')

@section('content')
    <div id="cb-canvas-root" class="cb-canvas-root">
        {!! $content !!}
    </div>

    @if (trim($content) === '')
        <div class="cb-canvas-empty">
            @if (! empty($region))
                {{-- A region editor opens empty even though the live site has a
                     header, because the theme's own markup is still doing that
                     job. Saying so is the whole point of this message. --}}
                <p class="cb-canvas-empty__title">Nothing built here yet</p>
                <p>
                    Your site is currently using the <strong>{{ $region }}</strong> that came with
                    your theme &mdash; that is what you see on the live site.
                </p>
                <p>
                    Anything you build here <strong>replaces</strong> it. Leave this empty and your
                    theme's version keeps being used.
                </p>
                <p class="cb-canvas-empty__hint">
                    Use <strong>Start from your theme</strong> on the left for a head start,
                    or pick a section layout and build your own.
                </p>
            @else
                <p class="cb-canvas-empty__title">This layout is empty</p>
                <p>Pick a section layout on the left, then drag widgets into it.</p>
            @endif
        </div>
    @endif
@endsection

@push('head')
    {{-- Editing chrome: outlines, drop targets and placeholders. None of this
         is ever served to a visitor. --}}
    <style id="cb-preview-styles">{!! $css !!}</style>
    <style>
        .cb-canvas-root { min-height: 40vh; }
        .cb-canvas-empty {
            margin: 3rem auto; max-width: 34rem; padding: 2.5rem 2rem;
            border: 2px dashed #cbd5e1; border-radius: 1rem;
            text-align: center; color: #64748b;
            font: 14px/1.65 system-ui, -apple-system, "Segoe UI", sans-serif;
        }
        .cb-canvas-empty p { margin: 0 0 .75rem; }
        .cb-canvas-empty p:last-child { margin-bottom: 0; }
        .cb-canvas-empty__title { font-size: 1.0625rem; font-weight: 600; color: #0f172a; }
        .cb-canvas-empty__hint {
            margin-top: 1.25rem; padding-top: 1rem;
            border-top: 1px solid #e2e8f0; font-size: 13px; color: #94a3b8;
        }
        [data-cb-id] { position: relative; }
        [data-cb-id]:hover { outline: 1px dashed rgba(37,99,235,.5); outline-offset: -1px; }
        .cb-empty-column {
            min-height: 76px; display: grid; place-items: center;
            border: 2px dashed #cbd5e1; border-radius: .5rem;
            color: #94a3b8; font: 13px system-ui, sans-serif;
        }
        .cb-placeholder {
            padding: 1rem; border: 1px dashed #cbd5e1; border-radius: .5rem;
            color: #94a3b8; font: 13px system-ui, sans-serif; text-align: center;
        }
    </style>
@endpush
