@extends('auth.layout')
@section('title', 'Two-factor authentication')
@section('subtitle', 'One more step to confirm it is you')

@section('content')
    <div x-data="{ recovery: false }" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        {{-- Authenticator code --}}
        <form x-show="! recovery" method="POST" action="{{ route('two-factor.verify') }}" class="space-y-4">
            @csrf

            <p class="text-sm text-slate-600">
                Open your authenticator app and enter the six-digit code for
                <strong>{{ auth()->user()->email }}</strong>.
            </p>

            <x-form.field label="Authentication code" name="code" required>
                <input type="text" name="code" id="code" required autofocus
                       inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                       class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono text-xl tracking-[0.4em] focus:border-indigo-500 focus:ring-indigo-500">
            </x-form.field>

            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Verify
            </button>

            <button type="button" @click="recovery = true" class="w-full text-center text-xs text-slate-500 hover:text-indigo-600">
                Lost your phone? Use a recovery code
            </button>
        </form>

        {{-- Recovery code --}}
        <form x-show="recovery" x-cloak method="POST" action="{{ route('two-factor.recovery') }}" class="space-y-4">
            @csrf

            <p class="text-sm text-slate-600">
                Enter one of the recovery codes you saved when you set up two-factor
                authentication. Each code works only once.
            </p>

            <x-form.field label="Recovery code" name="recovery_code" required>
                <x-form.input name="recovery_code" required autocomplete="one-time-code"
                              class="block w-full rounded-lg border border-slate-300 px-3 py-2.5 text-center font-mono text-sm" />
            </x-form.field>

            <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">
                Use recovery code
            </button>

            <button type="button" @click="recovery = false" class="w-full text-center text-xs text-slate-500 hover:text-indigo-600">
                Back to the authenticator code
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-5 border-t border-slate-100 pt-4">
            @csrf
            <button class="w-full text-center text-xs text-slate-400 hover:text-rose-600">Sign out instead</button>
        </form>
    </div>
@endsection
