{{--
    What the last import did.

    Counted per type and shown in full, including the failures: an import that
    quietly dropped nine posts and said "done" is worse than one that says
    which nine.
--}}
<x-admin.card title="Last import" :description="$report->summary()">
    @if ($report->counts())
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 font-medium">Type</th>
                        <th class="px-3 py-2 font-medium">Created</th>
                        <th class="px-3 py-2 font-medium">Updated</th>
                        <th class="px-3 py-2 font-medium">Skipped</th>
                        <th class="px-3 py-2 font-medium">Failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($report->counts() as $type => $counts)
                        <tr>
                            <td class="px-3 py-2 font-medium capitalize text-slate-700">{{ str_replace('_', ' ', $type) }}</td>
                            <td class="px-3 py-2 text-emerald-700">{{ $counts['created'] ?? 0 }}</td>
                            <td class="px-3 py-2 text-blue-700">{{ $counts['updated'] ?? 0 }}</td>
                            <td class="px-3 py-2 text-slate-500">{{ $counts['skipped'] ?? 0 }}</td>
                            <td class="px-3 py-2 {{ ($counts['failed'] ?? 0) > 0 ? 'font-semibold text-rose-600' : 'text-slate-400' }}">
                                {{ $counts['failed'] ?? 0 }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @foreach ($report->warnings() as $warning)
        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ $warning }}</p>
    @endforeach

    @if ($report->notes())
        <details class="mt-4 rounded-xl border border-slate-200">
            <summary class="cursor-pointer px-4 py-2.5 text-sm font-medium text-slate-700">Details</summary>
            <div class="space-y-3 border-t border-slate-100 px-4 py-3">
                @foreach ($report->notes() as $type => $notes)
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str_replace('_', ' ', $type) }}</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($notes as $note)
                                <li class="text-xs text-slate-600">{{ $note }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</x-admin.card>
