@extends('auth.layout')
@section('title', 'Create an account')
@section('subtitle', 'It only takes a moment')

@section('content')
    <form method="POST" action="{{ route('register.store') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <x-form.field label="Full name" name="name" required>
            <x-form.input name="name" required autofocus autocomplete="name" />
        </x-form.field>

        <x-form.field label="Email address" name="email" required>
            <x-form.input name="email" type="email" required autocomplete="email" />
        </x-form.field>

        <x-form.field label="Phone" name="phone">
            <x-form.input name="phone" autocomplete="tel" />
        </x-form.field>

        <x-form.field label="Password" name="password" required help="At least 8 characters, with letters and numbers.">
            <x-form.input name="password" type="password" required autocomplete="new-password" />
        </x-form.field>

        <x-form.field label="Confirm password" name="password_confirmation" required>
            <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-form.field>

        <label class="flex items-start gap-2 text-sm text-slate-600">
            <input type="checkbox" name="terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
            <span>I agree to the @if ($termsUrl = terms_url())<a href="{{ $termsUrl }}" target="_blank" rel="noopener" class="underline">terms and conditions</a>@else terms and conditions @endif</span>
        </label>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            Create account
        </button>

        <p class="text-center text-sm text-slate-500">
            Already registered? <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Sign in</a>
        </p>
    </form>
@endsection
