@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-md px-4 py-24 text-center">
        <h1 class="text-2xl font-bold text-slate-900">You have been unsubscribed</h1>
        <p class="mt-3 text-slate-600">
            {{ $subscriber->email }} will no longer receive our emails. If this was a mistake, you
            can subscribe again at any time.
        </p>
        <a href="{{ url('/') }}" class="mt-6 inline-block rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">
            Back to the site
        </a>
    </div>
@endsection
