@extends('auth.layout')
@section('title', 'Sign in')
@section('subtitle', 'Welcome back')

@section('content')
    <form method="POST" action="{{ route('login.attempt') }}"
          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf

        @if ($errors->any())
            <div class="rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700">{{ $errors->first() }}</div>
        @endif

        <x-form.field label="Email address" name="email" required>
            <x-form.input name="email" type="email" autocomplete="username" required autofocus />
        </x-form.field>

        <x-form.field label="Password" name="password" required>
            <x-form.input name="password" type="password" autocomplete="current-password" required />
        </x-form.field>

        <div class="flex items-center justify-between">
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                Remember me
            </label>
            <a href="{{ route('password.request') }}" class="text-sm text-indigo-600 hover:underline">Forgot password?</a>
        </div>

        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
            Sign in
        </button>

        @if (\App\Http\Controllers\Auth\OtpLoginController::enabled())
            <a href="{{ route('login.otp') }}"
               class="block w-full rounded-lg border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-700 hover:bg-slate-50">
                Sign in with a code sent to your phone
            </a>
        @endif

        @if (setting('registration_enabled', true))
            <p class="text-center text-sm text-slate-500">
                No account? <a href="{{ route('register') }}" class="text-indigo-600 hover:underline">Create one</a>
            </p>
        @endif
    </form>
@endsection
