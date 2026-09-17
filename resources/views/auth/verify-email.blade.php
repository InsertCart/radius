@extends('auth.layout')
@section('title', 'Verify your email')
@section('subtitle', 'One last step')

@section('content')
    <div class="space-y-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-600">
            We sent a verification link to <strong class="text-slate-900">{{ $user->email }}</strong>.
            Click it to finish setting up your account.
        </p>

        <p class="text-sm text-slate-600">
            Nothing there? Check your spam folder, or ask for a new link.
        </p>

        <form method="POST" action="{{ route('verification.send') }}">
            @csrf
            <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="text-center">
            @csrf
            <button type="submit" class="text-sm text-indigo-600 hover:underline">Sign out</button>
        </form>
    </div>
@endsection
