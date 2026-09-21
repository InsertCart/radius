@extends('theme::layout')

@section('content')
    <div class="zn-wrap" style="padding-top: 30px; padding-bottom: 80px;">
        <nav class="zn-crumbs" aria-label="Breadcrumb">
            <a href="{{ url('/') }}">Home</a>
            <span>/</span>
            <b>Concierge & Inquiries</b>
        </nav>

        <header style="max-width: 680px; margin-bottom: 44px;">
            <div class="zn-section-head__kicker">CLIENT RELATIONS</div>
            <h1 style="font-size: clamp(32px, 4.5vw, 48px); font-weight: 800; margin-bottom: 12px; letter-spacing: -0.03em;">
                Private Concierge
            </h1>
            <p style="color: var(--zn-muted); font-size: 16px; margin: 0; line-height: 1.6;">
                Our advisory team is at your service for acquisition guidance, commission queries, or general assistance.
            </p>
        </header>

        <div style="display: grid; grid-template-columns: 1fr 1.6fr; gap: 44px;">
            {{-- Contact Information Cards --}}
            <div style="display: flex; flex-direction: column; gap: 20px;">
                <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 28px;">
                    <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 14px;">Direct Channels</h3>

                    <div style="display: flex; flex-direction: column; gap: 16px; font-size: 14px;">
                        @if (setting('site_email'))
                            <div>
                                <span style="display: block; font-size: 11px; font-weight: 700; color: var(--zn-muted); text-transform: uppercase;">Email</span>
                                <a href="mailto:{{ setting('site_email') }}" style="font-weight: 600; color: var(--zn-accent);">{{ setting('site_email') }}</a>
                            </div>
                        @endif

                        @if (setting('site_phone'))
                            <div>
                                <span style="display: block; font-size: 11px; font-weight: 700; color: var(--zn-muted); text-transform: uppercase;">Telephone</span>
                                <a href="tel:{{ setting('site_phone') }}" style="font-weight: 600; color: var(--zn-text);">{{ setting('site_phone') }}</a>
                            </div>
                        @endif

                        @if (setting('site_address'))
                            <div>
                                <span style="display: block; font-size: 11px; font-weight: 700; color: var(--zn-muted); text-transform: uppercase;">Atelier Headquarters</span>
                                <p style="margin: 0; color: var(--zn-text-secondary); white-space: pre-line;">{{ setting('site_address') }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 28px;">
                    <h3 style="font-size: 16px; font-weight: 700; margin-bottom: 8px;">Hours of Operation</h3>
                    <p style="font-size: 13.5px; color: var(--zn-muted); margin: 0; line-height: 1.6;">
                        Monday &ndash; Friday: 09:00 &ndash; 18:00 CET<br>
                        Private appointments available on request.
                    </p>
                </div>
            </div>

            {{-- Inquiry Form --}}
            <div style="background: var(--zn-surface); border: 1px solid var(--zn-border); border-radius: var(--zn-radius-xl); padding: 36px;">
                <h3 style="font-size: 20px; font-weight: 800; margin-bottom: 8px;">Transmit Message</h3>
                <p style="font-size: 14px; color: var(--zn-muted); margin-bottom: 24px;">Complete the form below to receive a response within 24 hours.</p>

                <form method="POST" action="{{ route('contact.submit') }}" style="display: flex; flex-direction: column; gap: 18px;">
                    @csrf
                    <input type="text" name="website" tabindex="-1" autocomplete="off" style="display: none;" aria-hidden="true">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label for="name" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Your Name</label>
                            <input type="text" name="name" id="name" required value="{{ old('name') }}"
                                   style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="email" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Email Address</label>
                            <input type="email" name="email" id="email" required value="{{ old('email') }}"
                                   style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div>
                            <label for="phone" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Phone (optional)</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                                   style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                        </div>
                        <div>
                            <label for="subject" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Subject</label>
                            <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                                   style="width: 100%; height: 44px; padding: 0 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>

                    <div>
                        <label for="message" style="display: block; font-size: 12px; font-weight: 700; margin-bottom: 6px; text-transform: uppercase;">Message</label>
                        <textarea name="message" id="message" rows="5" required
                                  style="width: 100%; padding: 12px 14px; border-radius: var(--zn-radius-sm); border: 1px solid var(--zn-border); background: var(--zn-bg-alt); color: var(--zn-text); font-size: 14px; box-sizing: border-box; line-height: 1.5;">{{ old('message') }}</textarea>
                    </div>

                    <div>
                        <button type="submit" class="zn-btn zn-btn--primary zn-btn--lg">
                            Dispatch Inquiry
                            @include('theme::partials.icon', ['name' => 'arrow-right'])
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
