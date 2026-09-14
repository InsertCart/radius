@extends('theme::layout')

@section('content')
    <div class="sf-wrap sf-wrap--narrow sf-center sf-pad-xl">
        <h1 class="sf-section__title">You have been unsubscribed</h1>
        <p class="sf-muted sf-mt">
            {{ $subscriber->email }} will no longer receive our emails. If this was a mistake,
            you can subscribe again at any time.
        </p>
        <p class="sf-mt-lg">
            <a href="{{ url('/') }}" class="sf-btn sf-btn--primary">Back to the store</a>
        </p>
    </div>
@endsection
