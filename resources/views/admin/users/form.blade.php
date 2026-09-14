@extends('admin.layout')
@section('title', $user->exists ? 'Edit user' : 'New user')

@section('content')
    <div class="mx-auto max-w-2xl space-y-6">
        <form method="POST" action="{{ $user->exists ? route('admin.users.update', $user) : route('admin.users.store') }}">
            @csrf
            @if ($user->exists) @method('PUT') @endif

            <x-admin.card>
                <div class="space-y-5">
                    <x-form.field label="Name" name="name" required>
                        <x-form.input name="name" :value="$user->name" required autofocus />
                    </x-form.field>

                    <x-form.field label="Email address" name="email" required>
                        <x-form.input name="email" type="email" :value="$user->email" required />
                    </x-form.field>

                    <x-form.field label="Phone" name="phone">
                        <x-form.input name="phone" :value="$user->phone" />
                    </x-form.field>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.field label="Role" name="role" required
                                      :help="$user->id === auth()->id() ? 'You cannot change your own role.' : null">
                            <x-form.select name="role" :options="$roles" :value="$user->role"
                                           :disabled="$user->id === auth()->id()" />
                        </x-form.field>

                        <x-form.field label="Status" name="status" required>
                            <x-form.select name="status" :value="$user->status"
                                           :options="['active' => 'Active', 'suspended' => 'Suspended']"
                                           :disabled="$user->id === auth()->id()" />
                        </x-form.field>
                    </div>

                    <div class="grid gap-5 border-t border-slate-100 pt-5 sm:grid-cols-2">
                        <x-form.field label="Password" name="password"
                                      :help="$user->exists ? 'Leave blank to keep the current password.' : 'At least 8 characters, with letters and numbers.'"
                                      :required="! $user->exists">
                            <x-form.input name="password" type="password" autocomplete="new-password" />
                        </x-form.field>

                        <x-form.field label="Confirm password" name="password_confirmation">
                            <x-form.input name="password_confirmation" type="password" autocomplete="new-password" />
                        </x-form.field>
                    </div>
                </div>

                <div class="mt-6 flex gap-2 border-t border-slate-100 pt-5">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        {{ $user->exists ? 'Save changes' : 'Create user' }}
                    </button>
                    <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Cancel</a>
                </div>
            </x-admin.card>
        </form>

        @if ($user->exists)
            <x-admin.card title="Security">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-700">Two-factor authentication</p>
                        <p class="text-xs text-slate-500">
                            {{ $user->hasTwoFactorEnabled()
                                ? 'Enabled. Clear it only if this person has lost access to their authenticator app.'
                                : 'Not set up. Only the account holder can enable it.' }}
                        </p>
                    </div>
                    @if ($user->hasTwoFactorEnabled())
                        <form method="POST" action="{{ route('admin.users.two-factor.reset', $user) }}"
                              onsubmit="return confirm('Clear two-factor authentication for this user? They will sign in with just a password until they set it up again.')">
                            @csrf @method('DELETE')
                            <button class="shrink-0 rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                                Reset 2FA
                            </button>
                        </form>
                    @endif
                </div>

                @if ($user->id !== auth()->id())
                    <div class="mt-5 flex items-center justify-between gap-4 border-t border-slate-100 pt-5">
                        <div>
                            <p class="text-sm font-medium text-slate-700">Delete this account</p>
                            <p class="text-xs text-slate-500">Their orders and posts are kept, but unlinked.</p>
                        </div>
                        <form method="POST" action="{{ route('admin.users.destroy', $user) }}"
                              onsubmit="return confirm('Delete {{ $user->email }}? This cannot be undone.')">
                            @csrf @method('DELETE')
                            <button class="shrink-0 rounded-lg border border-rose-300 px-4 py-2 text-sm font-medium text-rose-600 hover:bg-rose-50">
                                Delete user
                            </button>
                        </form>
                    </div>
                @endif
            </x-admin.card>
        @endif
    </div>
@endsection
