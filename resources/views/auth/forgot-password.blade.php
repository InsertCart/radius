@extends('auth.layout')
@section('title', 'Reset your password')
@section('subtitle', 'We will email you a link')

@section('content')
    <form method="POST" action="{{ route('password.email') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        <p class="text-sm text-slate-600">
            Enter the address you signed up with and we will send you a link to choose a new password.
        </p>

        <x-form.field label="Email address" name="email" required>
            <x-form.input name="email" type="email" required autofocus autocomplete="email" />
        </x-form.field>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            Send reset link
        </button>

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Back to sign in</a>
        </p>
    </form>
@endsection
