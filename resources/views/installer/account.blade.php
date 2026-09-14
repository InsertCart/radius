@extends('installer.layout', ['step' => 'account'])
@section('title', 'Create your admin account')
@section('description', 'This is the account you will use to manage the site.')

@section('content')
    <form method="POST" action="{{ route('install.account.save') }}" class="space-y-5">
        @csrf

        <x-form.field label="Your name" name="name" required>
            <x-form.input name="name" required autofocus autocomplete="name" />
        </x-form.field>

        <x-form.field label="Email address" name="email" required>
            <x-form.input name="email" type="email" required autocomplete="email" />
        </x-form.field>

        <x-form.field label="Password" name="password" required
                      help="At least 10 characters, mixing upper and lower case letters with numbers.">
            <x-form.input name="password" type="password" required autocomplete="new-password" />
        </x-form.field>

        <x-form.field label="Confirm password" name="password_confirmation" required>
            <x-form.input name="password_confirmation" type="password" required autocomplete="new-password" />
        </x-form.field>

        <div class="rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-600">
            Once you are signed in, turn on two-factor authentication from your profile. It is the
            single most effective thing you can do to keep this account safe.
        </div>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Install
            </button>
            <a href="{{ route('install.site') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Back</a>
        </div>
    </form>
@endsection
