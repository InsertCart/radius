@extends('admin.layout')
@section('title', 'Subscribers')
@section('subtitle', $total.' active subscriber'.($total === 1 ? '' : 's'))

@section('content')
    <x-admin.card bodyClass="">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4">
            <form method="GET" class="flex-1">
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search by email"
                       class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </form>
            <a href="{{ route('admin.subscribers.export') }}"
               class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                Export CSV
            </a>
        </div>

        @if ($subscribers->isEmpty())
            <x-admin.empty message="No subscribers yet." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Email</th>
                            <th class="px-4 py-2.5 font-medium">Name</th>
                            <th class="px-4 py-2.5 font-medium">Source</th>
                            <th class="px-4 py-2.5 font-medium">Joined</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($subscribers as $subscriber)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 text-slate-700">{{ $subscriber->email }}</td>
                                <td class="px-4 py-3 text-slate-600">{{ $subscriber->name ?: '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ $subscriber->source ?: '—' }}</td>
                                <td class="px-4 py-3 text-xs text-slate-500">{{ format_date($subscriber->created_at) }}</td>
                                <td class="px-4 py-3">
                                    <x-admin.badge :color="$subscriber->status === 'subscribed' ? 'green' : 'gray'">
                                        {{ ucfirst($subscriber->status) }}
                                    </x-admin.badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('admin.subscribers.destroy', $subscriber) }}"
                                          onsubmit="return confirm('Remove this subscriber?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-rose-600 hover:underline">Remove</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $subscribers->links() }}</div>
        @endif
    </x-admin.card>
@endsection
