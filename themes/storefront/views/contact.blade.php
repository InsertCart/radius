@extends('theme::layout')

@section('content')
    <div class="sf-wrap sf-wrap--mid">
        <nav class="sf-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Contact</b>
        </nav>

        <header class="sf-phead">
            <h1>Get in touch</h1>
            <p>We usually reply within one working day.</p>
        </header>

        <div class="sf-listing">
            <aside class="sf-side">
                @if (setting('site_email'))
                    <div>
                        <h2 class="sf-side__title">Email</h2>
                        <a href="mailto:{{ setting('site_email') }}">{{ setting('site_email') }}</a>
                    </div>
                @endif
                @if (setting('site_phone'))
                    <div>
                        <h2 class="sf-side__title">Phone</h2>
                        <a href="tel:{{ setting('site_phone') }}">{{ setting('site_phone') }}</a>
                    </div>
                @endif
                @if (setting('site_address'))
                    <div>
                        <h2 class="sf-side__title">Address</h2>
                        <p class="sf-pre sf-muted sf-small">{{ setting('site_address') }}</p>
                    </div>
                @endif
            </aside>

            <form method="POST" action="{{ route('contact.submit') }}" class="sf-panel">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="sf-hp" aria-hidden="true">

                <div class="sf-fields sf-fields--2">
                    <div>
                        <label for="name" class="sf-label">Your name</label>
                        <input type="text" name="name" id="name" required class="sf-input" value="{{ old('name') }}">
                    </div>
                    <div>
                        <label for="email" class="sf-label">Email address</label>
                        <input type="email" name="email" id="email" required class="sf-input" value="{{ old('email') }}">
                    </div>
                    <div>
                        <label for="phone" class="sf-label">Phone (optional)</label>
                        <input type="text" name="phone" id="phone" class="sf-input" value="{{ old('phone') }}">
                    </div>
                    <div>
                        <label for="subject" class="sf-label">Subject</label>
                        <input type="text" name="subject" id="subject" class="sf-input" value="{{ old('subject') }}">
                    </div>
                </div>

                <div class="sf-field sf-mt">
                    <label for="message" class="sf-label">Message</label>
                    <textarea name="message" id="message" rows="6" required class="sf-textarea">{{ old('message') }}</textarea>
                </div>

                <button class="sf-btn sf-btn--primary sf-mt">Send message</button>
            </form>
        </div>
    </div>
@endsection
