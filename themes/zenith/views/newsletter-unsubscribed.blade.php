@extends('theme::layout')

@section('content')
    <div class="zn-wrap zn-wrap--narrow" style="padding: 100px 24px; text-align: center;">
        <span class="zn-badge zn-badge--accent" style="margin-bottom: 16px;">PREFERENCE UPDATED</span>
        <h1 style="font-size: 30px; font-weight: 800; margin-bottom: 12px;">You have been unsubscribed</h1>
        <p style="color: var(--zn-muted); font-size: 15px; max-width: 440px; margin: 0 auto 28px; line-height: 1.6;">
            {{ $subscriber->email }} will no longer receive private dispatches or circle notifications. You may re-join at any time.
        </p>
        <a href="{{ url('/') }}" class="zn-btn zn-btn--primary">
            Return to Store
            @include('theme::partials.icon', ['name' => 'arrow-right'])
        </a>
    </div>
@endsection
