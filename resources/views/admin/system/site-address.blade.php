@extends('admin.layout')
@section('title', 'Change site address')
@section('subtitle', 'Point every link at a new domain after moving the database')

@php
    $from = old('from', $from ?? '');
    $to = old('to', $to ?? $current);
@endphp

@section('content')
    <div class="space-y-6">

        <x-admin.card title="When to use this">
            <div class="space-y-2 text-sm text-slate-600">
                <p>
                    Use this after copying the database from one site to another, for example from
                    <span class="font-medium text-slate-800">https://staging.example.com</span> to
                    <span class="font-medium text-slate-800">https://www.example.com</span>.
                    Links and images in posts, pages, products, builder layouts, menus, SEO fields, redirects
                    and settings still point at the old address until you change them here.
                </p>
                <p>
                    You don't need this after <a href="{{ safe_route('admin.transfer.index', [], '#') }}" class="font-medium text-indigo-600 hover:underline">Import &amp; export</a>.
                    An import already points links at the site it runs on.
                </p>
                <p>
                    Uploaded files are not moved. Copy the <code class="rounded bg-slate-100 px-1 text-xs">storage/app/public</code>
                    folder to the new server too.
                </p>
            </div>

            @if ($appUrl !== $current)
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    <span class="font-semibold">APP_URL</span> in your .env file is <span class="font-semibold">{{ $appUrl }}</span>,
                    but this page was opened at <span class="font-semibold">{{ $current }}</span>.
                    Image addresses are built from APP_URL. Set it to the live address as well, then clear the caches.
                </p>
            @endif
        </x-admin.card>

        <x-admin.card title="Addresses">
            <form method="POST" action="{{ route('admin.system.site-address.preview') }}" class="space-y-4">
                @csrf

                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">Old address</span>
                        <input type="url" name="from" value="{{ $from }}" required placeholder="https://staging.example.com"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('from')
                            <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>

                    <label class="block text-sm">
                        <span class="font-medium text-slate-700">New address</span>
                        <input type="url" name="to" value="{{ $to }}" required placeholder="https://www.example.com"
                               class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('to')
                            <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                        @enderror
                    </label>
                </div>

                <p class="text-xs text-slate-500">
                    The old address is matched with http://, https:// or no scheme at all, so mixed links are fixed too.
                    Include the subfolder if the site lived in one (https://example.com/shop).
                </p>

                <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Find matches
                </button>
                <span class="ml-2 text-xs text-slate-500">Nothing is changed yet.</span>
            </form>
        </x-admin.card>

        @if (is_array($preview))
            <x-admin.card title="What will change">
                @if ($preview === [])
                    <p class="text-sm text-slate-600">
                        Nothing in the database points at <span class="font-medium text-slate-800">{{ $from }}</span>.
                        Check the old address. Try it with and without "www".
                    </p>
                @else
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-slate-100 text-left text-xs text-slate-500">
                                <th class="py-2 font-medium">Where</th>
                                <th class="py-2 text-right font-medium">Records</th>
                                <th class="py-2 text-right font-medium">Addresses</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($preview as $label => $counts)
                                <tr>
                                    <td class="py-2 text-slate-700">{{ $label }}</td>
                                    <td class="py-2 text-right text-slate-600">{{ number_format($counts['rows']) }}</td>
                                    <td class="py-2 text-right font-medium text-slate-800">{{ number_format($counts['links']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <form method="POST" action="{{ route('admin.system.site-address.apply') }}" class="mt-5 space-y-4 border-t border-slate-100 pt-4"
                          onsubmit="return confirm('Change every address shown above? This cannot be undone without the backup.')">
                        @csrf
                        <input type="hidden" name="from" value="{{ $from }}">
                        <input type="hidden" name="to" value="{{ $to }}">

                        <label class="flex items-start gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="backup" value="1" checked
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                            <span>
                                Back up the database first
                                <span class="mt-0.5 block text-xs text-slate-500">A .sql.gz file you can download under Updates → Backups and import with phpMyAdmin or mysql if you need to undo this.</span>
                            </span>
                        </label>

                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Change {{ number_format(array_sum(array_column($preview, 'links'))) }} address(es) to {{ $to }}
                        </button>
                    </form>
                @endif
            </x-admin.card>
        @endif
    </div>
@endsection
