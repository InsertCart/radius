@extends('admin.layout')
@section('title', 'Users')

@section('content')
    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex flex-1 flex-wrap gap-2">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search name or email"
                       class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="role" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Any role</option>
                    @foreach ($roles as $key => $label)
                        <option value="{{ $key }}" @selected(($filters['role'] ?? '') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            </form>

            <a href="{{ route('admin.users.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">New user</a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">User</th>
                        <th class="px-4 py-2.5 font-medium">Role</th>
                        <th class="px-4 py-2.5 font-medium">2FA</th>
                        <th class="px-4 py-2.5 font-medium">Last seen</th>
                        <th class="px-4 py-2.5 font-medium">Status</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($users as $user)
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">
                                        {{ $user->initials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.users.edit', $user) }}" class="font-medium text-slate-900 hover:text-indigo-600">
                                            {{ $user->name }}
                                        </a>
                                        <p class="truncate text-xs text-slate-400">{{ $user->email }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :color="$user->isAdmin() ? 'indigo' : ($user->isStaff() ? 'blue' : 'gray')">
                                    {{ $roles[$user->role] ?? $user->role }}
                                </x-admin.badge>
                            </td>
                            <td class="px-4 py-3">
                                @if ($user->hasTwoFactorEnabled())
                                    <span class="text-xs text-emerald-600">Enabled</span>
                                @else
                                    <span class="text-xs text-slate-400">Off</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-xs text-slate-500">
                                {{ $user->last_login_at?->diffForHumans() ?? 'Never' }}
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.badge :color="$user->isActive() ? 'green' : 'red'">
                                    {{ ucfirst($user->status) }}
                                </x-admin.badge>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    @if ($user->id !== auth()->id())
                                        <form method="POST" action="{{ route('admin.users.status', $user) }}">
                                            @csrf @method('PATCH')
                                            <button class="text-xs text-slate-500 hover:text-indigo-600">
                                                {{ $user->isActive() ? 'Suspend' : 'Reactivate' }}
                                            </button>
                                        </form>
                                    @endif
                                    <a href="{{ route('admin.users.edit', $user) }}" class="text-xs text-slate-500 hover:text-indigo-600">Edit</a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-100 p-4">{{ $users->links() }}</div>
    </x-admin.card>
@endsection
