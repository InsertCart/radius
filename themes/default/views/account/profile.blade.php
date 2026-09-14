@extends('theme::account.layout')
@section('heading', 'Your profile')

@section('account')
    <div class="space-y-6">
        <form method="POST" action="{{ route('account.profile.update') }}" class="rounded-2xl border border-slate-200 p-6">
            @csrf @method('PATCH')
            <h2 class="font-semibold text-slate-900">Details</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                    <input type="text" name="name" id="name" required value="{{ old('name', $user->name) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-sm font-medium text-slate-700">Email address</label>
                    <input type="email" name="email" id="email" required value="{{ old('email', $user->email) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="phone" class="mb-1 block text-sm font-medium text-slate-700">Phone</label>
                    <input type="text" name="phone" id="phone" value="{{ old('phone', $user->phone) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <button class="mt-5 rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">Save changes</button>
        </form>

        <form method="POST" action="{{ route('account.password.update') }}" class="rounded-2xl border border-slate-200 p-6">
            @csrf @method('PATCH')
            <h2 class="font-semibold text-slate-900">Change password</h2>

            <div class="mt-4 space-y-4">
                <div>
                    <label for="current_password" class="mb-1 block text-sm font-medium text-slate-700">Current password</label>
                    <input type="password" name="current_password" id="current_password" required autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="password" class="mb-1 block text-sm font-medium text-slate-700">New password</label>
                    <input type="password" name="password" id="password" required autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label for="password_confirmation" class="mb-1 block text-sm font-medium text-slate-700">Confirm new password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" required autocomplete="new-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            <button class="mt-5 rounded-lg px-5 py-2.5 text-sm font-semibold text-white btn-brand">Change password</button>
        </form>
    </div>
@endsection
