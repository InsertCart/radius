@extends('auth.layout')
@section('title', 'Choose a new password')
@section('subtitle', 'Almost there')

@section('content')
    <form method="POST" action="{{ route('password.update') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <x-form.field label="Email address" name="email" required>
            <x-form.input name="email" type="email" :value="$email" required readonly
                          class="block w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm" />
        </x-form.field>

        <x-form.field label="New password" name="password" required help="At least 8 characters, with letters and numbers.">
            <x-form.input name="password" type="password" required autofocus autocomplete="new-password" />
        </x-form.field>

        <x-form.field label="Confirm new password" name="password_confirmation" required>
            <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-form.field>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            Reset password
        </button>
    </form>
@endsection
