@extends('admin.layout')
@section('title', 'Redirects')
@section('subtitle', 'Point old URLs at their replacements so links and rankings survive')

@section('content')
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <x-admin.card bodyClass="">
                <form method="GET" class="border-b border-slate-100 p-4">
                    <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search source paths"
                           class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </form>

                @if ($redirects->isEmpty())
                    <x-admin.empty message="No redirects yet." />
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th class="px-4 py-2.5 font-medium">From</th>
                                    <th class="px-4 py-2.5 font-medium">To</th>
                                    <th class="px-4 py-2.5 font-medium">Code</th>
                                    <th class="px-4 py-2.5 font-medium">Hits</th>
                                    <th class="px-4 py-2.5"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach ($redirects as $redirect)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 font-mono text-xs">/{{ $redirect->source }}</td>
                                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $redirect->destination }}</td>
                                        <td class="px-4 py-3">
                                            <x-admin.badge :color="$redirect->status_code === 301 ? 'green' : 'amber'">
                                                {{ $redirect->status_code }}
                                            </x-admin.badge>
                                        </td>
                                        <td class="px-4 py-3 text-slate-600">{{ number_format($redirect->hits) }}</td>
                                        <td class="px-4 py-3 text-right">
                                            <form method="POST" action="{{ route('admin.seo.redirects.destroy', $redirect) }}"
                                                  onsubmit="return confirm('Remove this redirect?')">
                                                @csrf @method('DELETE')
                                                <button class="text-xs text-rose-600 hover:underline">Remove</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-slate-100 p-4">{{ $redirects->links() }}</div>
                @endif
            </x-admin.card>
        </div>

        <x-admin.card title="Add a redirect">
            <form method="POST" action="{{ route('admin.seo.redirects.store') }}" class="space-y-4">
                @csrf

                <x-form.field label="From" name="source" required help="A path on this site, without the leading slash.">
                    <x-form.input name="source" placeholder="old-page" required />
                </x-form.field>

                <x-form.field label="To" name="destination" required help="A path on this site, or a full URL elsewhere.">
                    <x-form.input name="destination" placeholder="new-page" required />
                </x-form.field>

                <x-form.field label="Status code" name="status_code" help="Use 301 for a permanent move.">
                    <x-form.select name="status_code" value="301" :options="[
                        301 => '301 — Moved permanently',
                        302 => '302 — Found (temporary)',
                        307 => '307 — Temporary redirect',
                        308 => '308 — Permanent redirect',
                    ]" />
                </x-form.field>

                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Add redirect
                </button>
            </form>
        </x-admin.card>
    </div>
@endsection
