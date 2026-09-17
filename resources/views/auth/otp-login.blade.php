@extends('auth.layout')
@section('title', 'Sign in with a code')
@section('subtitle', 'No password needed')

@section('content')
    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        @if (! $pending)
            <form method="POST" action="{{ route('login.otp.send') }}" class="space-y-4">
                @csrf

                <p class="text-sm text-slate-600">
                    Enter the mobile number saved on your account and we will text you a six-digit code.
                </p>

                <x-form.field label="Mobile number" name="phone" required>
                    <x-form.input name="phone" type="tel" autocomplete="tel" required autofocus />
                </x-form.field>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Text me a code
                </button>
            </form>
        @else
            <form method="POST" action="{{ route('login.otp.verify') }}" class="space-y-4">
                @csrf

                <p class="text-sm text-slate-600">
                    Enter the code sent to <strong class="text-slate-900">{{ $pending['typed'] }}</strong>. It expires in 10 minutes.
                </p>

                <x-form.field label="Code" name="code" required>
                    <x-form.input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                                  autocomplete="one-time-code" required autofocus />
                </x-form.field>

                <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Sign in
                </button>
            </form>

            <form method="POST" action="{{ route('login.otp.cancel') }}" class="text-center">
                @csrf
                <button type="submit" class="text-sm text-indigo-600 hover:underline">Use a different number or send a new code</button>
            </form>
        @endif

        <p class="text-center text-sm text-slate-500">
            <a href="{{ route('login') }}" class="text-indigo-600 hover:underline">Sign in with your password instead</a>
        </p>
    </div>
@endsection
