{{-- Inline icon set. Usage: @include('theme::partials.icon', ['name' => 'bag'])
     Kept as one file so the whole theme draws from a single visual vocabulary
     and no icon font or external request is needed. --}}
@switch($name)
    @case('search')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        @break

    @case('heart')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 20s-7.5-4.7-7.5-9.6A4.4 4.4 0 0 1 12 7.6a4.4 4.4 0 0 1 7.5 2.8C19.5 15.3 12 20 12 20Z"/>
        </svg>
        @break

    @case('user')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true">
            <circle cx="12" cy="8.5" r="3.6"/><path d="M4.8 20a7.2 7.2 0 0 1 14.4 0"/>
        </svg>
        @break

    @case('bag')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true">
            <path d="M5 8h14l-1 12H6L5 8Z"/><path d="M9 8V6.5a3 3 0 0 1 6 0V8"/>
        </svg>
        @break

    @case('menu')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="M4 7h16M4 12h16M4 17h16"/>
        </svg>
        @break

    @case('close')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <path d="m6 6 12 12M18 6 6 18"/>
        </svg>
        @break

    @case('chevron-down')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m6 9 6 6 6-6"/>
        </svg>
        @break

    @case('chevron-left')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m14 6-6 6 6 6"/>
        </svg>
        @break

    @case('chevron-right')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m10 6 6 6-6 6"/>
        </svg>
        @break

    @case('arrow-up-right')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M7 17 17 7M9 7h8v8"/>
        </svg>
        @break

    @case('tag')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round" aria-hidden="true">
            <path d="M11 3H4v7l10 10 7-7L11 3Z"/><circle cx="7.5" cy="7.5" r="1.3" fill="currentColor" stroke="none"/>
        </svg>
        @break

    @case('verified')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m12 3 2.3 1.7 2.8-.3 1 2.7 2.4 1.5-.9 2.7.9 2.7-2.4 1.5-1 2.7-2.8-.3L12 21l-2.3-1.8-2.8.3-1-2.7L3.5 15l.9-2.7-.9-2.7 2.4-1.5 1-2.7 2.8.3L12 3Z"/>
            <path d="m9.2 12 2 2 3.6-3.8"/>
        </svg>
        @break

    @case('leaf')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M20 4C10 4 4.5 8 4.5 14.5A5.5 5.5 0 0 0 10 20c6.5 0 10-5.5 10-16Z"/><path d="M4 20 14 10"/>
        </svg>
        @break

    @case('shield')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3.5 5 6v6c0 4.2 2.9 7.4 7 8.5 4.1-1.1 7-4.3 7-8.5V6l-7-2.5Z"/><path d="m9.2 12 2 2 3.6-3.8"/>
        </svg>
        @break

    @case('truck')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 7h11v9H3zM14 10h4l3 3v3h-7z"/><circle cx="7" cy="18" r="1.7"/><circle cx="17.5" cy="18" r="1.7"/>
        </svg>
        @break

    @case('download')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M12 3v12m0 0-4-4m4 4 4-4M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>
        </svg>
        @break

    @case('logo')
        <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true">
            <path d="M6 5h13a5 5 0 0 1 5 5v17H11a5 5 0 0 1-5-5V5Z"/><path d="M10 5v22"/>
        </svg>
        @break

    @case('facebook')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M13.5 21v-8h2.7l.4-3h-3.1V8.2c0-.9.3-1.5 1.5-1.5h1.7V4a22 22 0 0 0-2.4-.1c-2.5 0-4.2 1.5-4.2 4.2V10H7.4v3h2.7v8h3.4Z"/>
        </svg>
        @break

    @case('instagram')
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" aria-hidden="true">
            <rect x="3.5" y="3.5" width="17" height="17" rx="5"/><circle cx="12" cy="12" r="3.8"/><circle cx="17" cy="7" r="1" fill="currentColor" stroke="none"/>
        </svg>
        @break

    @case('twitter')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M17.5 3h3l-6.6 7.5L21.7 21h-6l-4.7-6-5.4 6H2.6l7-8L2.3 3h6.2l4.2 5.6L17.5 3Zm-1.1 16h1.7L7.7 4.8H5.9L16.4 19Z"/>
        </svg>
        @break

    @case('linkedin')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M6.9 20V9.4H3.6V20h3.3ZM5.2 8a1.9 1.9 0 1 0 0-3.9 1.9 1.9 0 0 0 0 3.9ZM20.4 20v-6.1c0-3-1.6-4.4-3.8-4.4-1.7 0-2.5.9-3 1.6V9.4H10.4V20h3.3v-5.9c0-1.3.6-2.1 1.7-2.1s1.7.8 1.7 2.1V20h3.3Z"/>
        </svg>
        @break

    @case('youtube')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M21.6 7.6a2.5 2.5 0 0 0-1.8-1.8C18.2 5.4 12 5.4 12 5.4s-6.2 0-7.8.4A2.5 2.5 0 0 0 2.4 7.6 26 26 0 0 0 2 12a26 26 0 0 0 .4 4.4 2.5 2.5 0 0 0 1.8 1.8c1.6.4 7.8.4 7.8.4s6.2 0 7.8-.4a2.5 2.5 0 0 0 1.8-1.8A26 26 0 0 0 22 12a26 26 0 0 0-.4-4.4ZM10 15V9l5.2 3L10 15Z"/>
        </svg>
        @break

    @case('whatsapp')
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M12 2a10 10 0 0 0-8.6 15L2 22l5.2-1.4A10 10 0 1 0 12 2zm5.8 14.2c-.2.7-1.4 1.3-2 1.4-.5.1-1.2.1-1.9-.1-.4-.1-1-.3-1.8-.6-3.1-1.3-5.1-4.4-5.3-4.6-.1-.2-1.2-1.6-1.2-3s.7-2.1 1-2.4c.3-.3.6-.4.8-.4h.6c.2 0 .4 0 .7.5l.9 2.2c.1.2.1.4 0 .6l-.4.5-.3.3c-.1.1-.3.3-.1.6.2.3.8 1.3 1.7 2.1 1.1 1 2 1.3 2.3 1.4.3.1.5.1.7-.1l.9-1c.2-.2.4-.2.6-.1l2.1 1c.3.1.5.2.5.3.1.2.1.6-.1 1.2z"/>
        </svg>
        @break
@endswitch
