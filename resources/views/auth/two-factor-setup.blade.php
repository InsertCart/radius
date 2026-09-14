@extends('auth.layout')
@section('title', 'Two-factor authentication')
@section('subtitle', 'Protect your account with a second step')

@section('content')
    @if (session('two_factor.recovery_codes'))
        <div class="mb-4 rounded-2xl border border-amber-300 bg-amber-50 p-5">
            <h2 class="text-sm font-semibold text-amber-900">Save your recovery codes</h2>
            <p class="mt-1 text-xs text-amber-800">
                These are the only way back into your account if you lose your phone. Each one
                works once. This is the only time they are shown.
            </p>
            <div class="mt-3 grid grid-cols-2 gap-1.5 rounded-lg bg-white p-3 font-mono text-xs">
                @foreach (session('two_factor.recovery_codes') as $code)
                    <span class="text-slate-700">{{ $code }}</span>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        @if ($enabled)
            <div class="flex items-start gap-3">
                <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-100 text-emerald-700">&check;</span>
                <div>
                    <h2 class="text-sm font-semibold text-slate-900">Two-factor authentication is on</h2>
                    <p class="mt-1 text-sm text-slate-600">
                        You will be asked for a code from your authenticator app every time you sign in.
                    </p>
                </div>
            </div>

            <div class="mt-6 space-y-3 border-t border-slate-100 pt-5">
                <form method="POST" action="{{ route('two-factor.recovery-codes') }}">
                    @csrf
                    <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Generate new recovery codes
                    </button>
                    <p class="mt-1 text-xs text-slate-500">Your existing codes stop working immediately.</p>
                </form>

                @unless (setting('admin_2fa_required', false) && auth()->user()->isStaff())
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="space-y-2"
                          onsubmit="return confirm('Turn off two-factor authentication?')">
                        @csrf @method('DELETE')
                        <input type="password" name="current_password" required placeholder="Confirm your password"
                               class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                            Turn off two-factor authentication
                        </button>
                    </form>
                @endunless
            </div>

        @elseif ($pending)
            <h2 class="text-sm font-semibold text-slate-900">Scan this code</h2>
            <p class="mt-1 text-sm text-slate-600">
                Open Google Authenticator, Authy, 1Password or any other authenticator app and scan
                the code below.
            </p>

            <div class="my-5 flex justify-center rounded-xl border border-slate-200 bg-white p-4">
                {!! $qrCode !!}
            </div>

            <details class="mb-5 text-xs text-slate-500">
                <summary class="cursor-pointer hover:text-slate-700">Cannot scan it?</summary>
                <p class="mt-2">Enter this key into your app by hand:</p>
                <code class="mt-1 block break-all rounded bg-slate-100 p-2 font-mono text-slate-700">{{ $secret }}</code>
            </details>

            <form method="POST" action="{{ route('two-factor.confirm') }}" class="space-y-4">
                @csrf

                <x-form.field label="Enter the six-digit code from your app" name="code" required>
                    <input type="text" name="code" id="code" required autofocus
                           inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                           class="block w-full rounded-lg border border-slate-300 px-3 py-3 text-center font-mono text-xl tracking-[0.4em] focus:border-indigo-500 focus:ring-indigo-500">
                </x-form.field>

                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Confirm and turn on
                </button>
            </form>

        @else
            <h2 class="text-sm font-semibold text-slate-900">Add a second step to sign in</h2>
            <p class="mt-1 text-sm text-slate-600">
                With two-factor authentication on, signing in needs both your password and a code
                from your phone. A stolen password on its own is no longer enough.
            </p>

            @if (setting('admin_2fa_required', false) && auth()->user()->isStaff())
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This site requires two-factor authentication for admin accounts.
                </p>
            @endif

            <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-5">
                @csrf
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                    Set up two-factor authentication
                </button>
            </form>
        @endif

        <p class="mt-5 border-t border-slate-100 pt-4 text-center">
            <a href="{{ auth()->user()->isStaff() ? route('admin.dashboard') : route('account.dashboard') }}"
               class="text-xs text-slate-500 hover:text-indigo-600">
                &larr; Back to {{ auth()->user()->isStaff() ? 'the admin panel' : 'your account' }}
            </a>
        </p>
    </div>
@endsection
