@extends('theme::layout')

@section('content')
    <div class="mx-auto max-w-5xl px-4 py-14">
        <header class="mb-10 text-center">
            <h1 class="text-3xl font-bold tracking-tight text-slate-900">Get in touch</h1>
            <p class="mt-2 text-slate-600">We usually reply within one working day.</p>
        </header>

        <div class="grid gap-10 md:grid-cols-3">
            <div class="space-y-6 text-sm">
                @if (setting('site_email'))
                    <div>
                        <p class="font-semibold text-slate-900">Email</p>
                        <a href="mailto:{{ setting('site_email') }}" class="text-slate-600 hover:text-brand">{{ setting('site_email') }}</a>
                    </div>
                @endif
                @if (setting('site_phone'))
                    <div>
                        <p class="font-semibold text-slate-900">Phone</p>
                        <a href="tel:{{ setting('site_phone') }}" class="text-slate-600 hover:text-brand">{{ setting('site_phone') }}</a>
                    </div>
                @endif
                @if (setting('site_address'))
                    <div>
                        <p class="font-semibold text-slate-900">Address</p>
                        <p class="whitespace-pre-line text-slate-600">{{ setting('site_address') }}</p>
                    </div>
                @endif
            </div>

            <form method="POST" action="{{ route('contact.submit') }}" class="space-y-4 md:col-span-2">
                @csrf
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="hidden" aria-hidden="true">

                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Your name</label>
                        <input type="text" name="name" id="name" required value="{{ old('name') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                        <input type="email" name="email" id="email" required value="{{ old('email') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone (optional)</label>
                        <input type="text" name="phone" id="phone" value="{{ old('phone') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label for="subject" class="mb-1 block text-sm font-medium text-slate-700">Subject</label>
                        <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                </div>

                <div>
                    <label for="message" class="mb-1 block text-sm font-medium text-slate-700">Message</label>
                    <textarea name="message" id="message" rows="6" required
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('message') }}</textarea>
                </div>

                <button class="rounded-lg px-6 py-2.5 text-sm font-semibold text-white btn-brand">Send message</button>
            </form>
        </div>
    </div>
@endsection
