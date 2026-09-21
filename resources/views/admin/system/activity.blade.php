@extends('admin.layout')
@section('title', 'Activity log')

@section('content')
    @php $retention = \App\Models\ActivityLog::retentionDays(); @endphp
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
        <p>
            @if ($retention > 0)
                Entries older than <strong>{{ $retention }} {{ Str::plural('day', $retention) }}</strong> are deleted automatically to save disk space.
            @else
                Entries are kept forever, so this log will keep growing.
            @endif
        </p>
        <a href="{{ route('admin.settings.edit', 'advanced') }}" class="font-medium text-indigo-600 hover:text-indigo-700">
            Change in Settings &rarr;
        </a>
    </div>

    <x-admin.card bodyClass="">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-slate-100 p-4">
            <select name="action" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">All actions</option>
                @foreach ($actions as $action)
                    <option value="{{ $action }}" @selected(($filters['action'] ?? '') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
        </form>

        @if ($logs->isEmpty())
            <x-admin.empty message="Nothing logged yet." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">When</th>
                            <th class="px-4 py-2.5 font-medium">Who</th>
                            <th class="px-4 py-2.5 font-medium">Action</th>
                            <th class="px-4 py-2.5 font-medium">Detail</th>
                            <th class="px-4 py-2.5 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($logs as $log)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500">
                                    {{ format_date($log->created_at, 'd M, H:i') }}
                                </td>
                                <td class="px-4 py-3 text-slate-600">{{ $log->user?->name ?? 'System' }}</td>
                                <td class="px-4 py-3"><code class="text-xs text-slate-500">{{ $log->action }}</code></td>
                                <td class="px-4 py-3 text-slate-700">{{ $log->description }}</td>
                                <td class="px-4 py-3 text-xs text-slate-400">{{ $log->ip_address }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $logs->links() }}</div>
        @endif
    </x-admin.card>
@endsection
