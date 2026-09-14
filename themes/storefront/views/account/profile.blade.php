@extends('theme::account.layout')
@section('heading', 'Your profile')

@section('account')
    <form method="POST" action="{{ route('account.profile.update') }}" class="sf-panel">
        @csrf @method('PATCH')
        <p class="sf-panel__title">Details</p>

        <div class="sf-field">
            <label for="name" class="sf-label">Name</label>
            <input type="text" name="name" id="name" required class="sf-input" value="{{ old('name', $user->name) }}">
        </div>

        <div class="sf-field">
            <label for="email" class="sf-label">Email address</label>
            <input type="email" name="email" id="email" required class="sf-input" value="{{ old('email', $user->email) }}">
        </div>

        <div class="sf-field">
            <label for="phone" class="sf-label">Phone</label>
            <input type="text" name="phone" id="phone" class="sf-input" value="{{ old('phone', $user->phone) }}">
        </div>

        <button class="sf-btn sf-btn--primary sf-mt">Save changes</button>
    </form>

    <form method="POST" action="{{ route('account.password.update') }}" class="sf-panel">
        @csrf @method('PATCH')
        <p class="sf-panel__title">Change password</p>

        <div class="sf-field">
            <label for="current_password" class="sf-label">Current password</label>
            <input type="password" name="current_password" id="current_password" required
                   autocomplete="current-password" class="sf-input">
        </div>

        <div class="sf-field">
            <label for="password" class="sf-label">New password</label>
            <input type="password" name="password" id="password" required autocomplete="new-password" class="sf-input">
        </div>

        <div class="sf-field">
            <label for="password_confirmation" class="sf-label">Confirm new password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                   autocomplete="new-password" class="sf-input">
        </div>

        <button class="sf-btn sf-btn--primary sf-mt">Change password</button>
    </form>
@endsection
