@php $action = safe_route('contact.submit', [], '#'); @endphp

<form class="cb-form" method="POST" action="{{ $action }}">
    @csrf
    {{-- Honeypot: invisible to people, irresistible to bots. --}}
    <input type="text" name="website" tabindex="-1" autocomplete="off" class="cb-hp" aria-hidden="true">

    <div class="cb-form__row">
        <label class="cb-form__field">
            <span>Your name</span>
            <input type="text" name="name" required value="{{ old('name') }}">
        </label>

        <label class="cb-form__field">
            <span>Email address</span>
            <input type="email" name="email" required value="{{ old('email') }}">
        </label>
    </div>

    @if (! empty($settings['show_phone']) || ! empty($settings['show_subject']))
        <div class="cb-form__row">
            @if (! empty($settings['show_phone']))
                <label class="cb-form__field">
                    <span>Phone</span>
                    <input type="text" name="phone" value="{{ old('phone') }}">
                </label>
            @endif

            @if (! empty($settings['show_subject']))
                <label class="cb-form__field">
                    <span>Subject</span>
                    <input type="text" name="subject" value="{{ old('subject') }}">
                </label>
            @endif
        </div>
    @endif

    <label class="cb-form__field">
        <span>Message</span>
        <textarea name="message" rows="5" required>{{ old('message') }}</textarea>
    </label>

    <div class="cb-form__actions">
        <button type="submit" class="cb-button cb-button--md">
            {{ $settings['button_text'] ?? 'Send message' }}
        </button>
    </div>
</form>
