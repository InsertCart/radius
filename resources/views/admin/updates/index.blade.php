@extends('admin.layout')
@section('title', 'Updates')
@section('subtitle', 'Install new releases without losing your content or settings')

@section('content')
    @if ($pending)
        {{-- A part-finished update is the most urgent thing on this screen. --}}
        <div class="mb-5 rounded-xl border border-amber-300 bg-amber-50 px-4 py-4">
            <h2 class="text-sm font-semibold text-amber-900">An update is part-way through</h2>
            <p class="mt-1 text-sm text-amber-800">
                Version {{ $pending['to'] ?? '' }} was installed over {{ $pending['from'] ?? '' }}, but the
                finishing steps did not complete. Finish them now, or put the previous version back.
            </p>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('admin.updates.finalize') }}"
                   class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Finish the update
                </a>
                <form method="POST" action="{{ route('admin.updates.rollback') }}"
                      onsubmit="return confirm('Put the previous version back? Files changed by the update will be restored.')">
                    @csrf
                    <label class="mr-2 text-xs text-amber-800">
                        <input type="checkbox" name="restore_database" value="1" class="rounded border-amber-400">
                        also restore the database
                    </label>
                    <button class="rounded-lg border border-amber-400 px-4 py-2 text-sm font-medium text-amber-800 hover:bg-amber-100">
                        Roll back
                    </button>
                </form>
            </div>
        </div>
    @endif

    @if (! $pending && $lastUpdate)
        {{-- An update can finish cleanly and still turn out to have broken
             something, so the way back stays open until the next one. --}}
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm">
            <span class="text-slate-600">
                Updated from <strong class="text-slate-900">{{ $lastUpdate['from'] ?? '' }}</strong> to
                <strong class="text-slate-900">{{ $lastUpdate['to'] ?? '' }}</strong>
                @if (! empty($lastUpdate['completed_at']))
                    {{ \Illuminate\Support\Carbon::parse($lastUpdate['completed_at'])->diffForHumans() }}
                @endif
                . Something wrong? You can still put the previous version back.
            </span>
            <form method="POST" action="{{ route('admin.updates.rollback') }}"
                  onsubmit="return confirm('Put version {{ $lastUpdate['from'] ?? '' }} back?')">
                @csrf
                <label class="mr-2 text-xs text-slate-500">
                    <input type="checkbox" name="restore_database" value="1" class="rounded border-slate-300">
                    also restore the database
                </label>
                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-medium hover:bg-slate-50">
                    Roll back
                </button>
            </form>
        </div>
    @endif

    <div class="grid gap-5 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <x-admin.card>
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Installed</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $current }}</p>
                        @if ($lastChecked)
                            <p class="mt-1 text-xs text-slate-400">
                                Last checked {{ \Illuminate\Support\Carbon::parse($lastChecked)->diffForHumans() }}
                            </p>
                        @endif
                    </div>

                    @if ($available)
                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">Available</p>
                            <p class="mt-1 text-2xl font-semibold text-indigo-600">{{ $manifest->version }}</p>
                            @if ($manifest->humanSize())
                                <p class="mt-1 text-xs text-slate-400">{{ $manifest->humanSize() }} download</p>
                            @endif
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.updates.check') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Check now
                        </button>
                    </form>
                </div>
            </x-admin.card>

            @unless ($configured)
                <x-admin.card title="No update address set">
                    <p class="text-sm text-slate-600">
                        Add <code class="rounded bg-slate-100 px-1">CMS_UPDATE_URL</code> to your
                        <code class="rounded bg-slate-100 px-1">.env</code> file, pointing at the JSON file that
                        describes your latest release. Until then this site will never look for updates.
                    </p>
                </x-admin.card>
            @endunless

            @if ($error)
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $error }}</div>
            @endif

            @if ($configured && ! $available && ! $error && $manifest)
                <x-admin.card>
                    <p class="text-sm text-slate-600">
                        <span class="font-medium text-slate-900">You are up to date.</span>
                        The latest release is {{ $manifest->version }}.
                    </p>
                </x-admin.card>
            @endif

            @if ($available)
                <x-admin.card title="Version {{ $manifest->version }}"
                              description="{{ $manifest->releasedAt ? 'Released '.$manifest->releasedAt : '' }}">
                    @if ($manifest->tags)
                        <div class="mb-3 flex flex-wrap gap-1.5">
                            @foreach ($manifest->tags as $tag)
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium
                                    {{ $manifest->isSecurityRelease() && in_array($tag, ['security', 'secure', 'critical', 'vulnerability'])
                                        ? 'bg-rose-100 text-rose-700'
                                        : 'bg-slate-100 text-slate-600' }}">
                                    {{ $tag }}
                                </span>
                            @endforeach
                        </div>
                    @endif

                    @if ($manifest->notes)
                        <p class="text-sm text-slate-700">{{ $manifest->notes }}</p>
                    @endif

                    @if ($manifest->changelogUrl)
                        <p class="mt-2 text-sm">
                            <a href="{{ $manifest->changelogUrl }}" target="_blank" rel="noopener"
                               class="font-medium text-indigo-600 hover:underline">Read the full changelog</a>
                        </p>
                    @endif

                    @if ($manifest->isSecurityRelease())
                        <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-800">
                            This release is marked as a security fix. Install it as soon as you can.
                        </p>
                    @endif
                </x-admin.card>

                <x-admin.card title="Before installing" description="Everything is checked before a single file is touched.">
                    <ul class="space-y-2 text-sm">
                        @foreach ($checks as $check)
                            <li class="flex gap-2.5">
                                @if ($check['passed'])
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                    </svg>
                                @else
                                    <svg class="mt-0.5 h-4 w-4 shrink-0 {{ $check['fatal'] ? 'text-rose-600' : 'text-amber-500' }}" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" d="M12 8v5m0 3.5h.01M10.3 3.9L2.4 17a2 2 0 0 0 1.7 3h15.8a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/>
                                    </svg>
                                @endif
                                <span>
                                    <span class="font-medium text-slate-800">{{ $check['label'] }}</span>
                                    <span class="text-slate-500"> — {{ $check['message'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </x-admin.card>

                @if ($modified)
                    <x-admin.card title="Files you have edited">
                        <p class="text-sm text-slate-600">
                            These shipped files no longer match what was installed, so someone has changed them on
                            this site. They will be <strong>left exactly as they are</strong> unless you choose
                            otherwise below — which means any fixes this release makes to them will not arrive.
                        </p>
                        <ul class="mt-3 max-h-48 space-y-0.5 overflow-y-auto rounded-lg bg-slate-50 p-3 font-mono text-xs text-slate-600">
                            @foreach ($modified as $file)
                                <li>{{ $file }}</li>
                            @endforeach
                        </ul>
                    </x-admin.card>
                @elseif (! $hasBaseline)
                    <div class="rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                        This is the first update since edit tracking was added, so the CMS cannot yet tell which
                        shipped files you have customised. If you have edited any, copy them somewhere safe first.
                        From the next release onwards they will be detected and protected automatically.
                    </div>
                @endif

                <x-admin.card title="Install version {{ $manifest->version }}">
                    <form method="POST" action="{{ route('admin.updates.apply') }}" class="space-y-4"
                          onsubmit="this.querySelector('button[type=submit]').disabled = true;
                                    this.querySelector('button[type=submit]').textContent = 'Updating — do not close this page…';">
                        @csrf

                        <label class="flex items-start gap-2.5 text-sm">
                            <input type="checkbox" name="backup_database" value="1" checked
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                            <span>
                                <span class="font-medium text-slate-800">Back up the database first</span>
                                <span class="block text-xs text-slate-500">
                                    Strongly recommended{{ $manifest->requiresBackup ? ' — this release is marked as needing one' : '' }}.
                                </span>
                            </span>
                        </label>

                        @if ($modified)
                            <label class="flex items-start gap-2.5 text-sm">
                                <input type="checkbox" name="overwrite_edited" value="1"
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-rose-600">
                                <span>
                                    <span class="font-medium text-slate-800">Overwrite my {{ count($modified) }} edited file(s)</span>
                                    <span class="block text-xs text-slate-500">
                                        Your changes to them will be lost, but they are copied into the backup first.
                                    </span>
                                </span>
                            </label>
                        @endif

                        <label class="flex items-start gap-2.5 text-sm">
                            <input type="checkbox" name="confirm" value="1" required
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                            <span class="font-medium text-slate-800">
                                I understand the site will be briefly unavailable while this runs.
                            </span>
                        </label>

                        <button type="submit" @disabled(! $available)
                                class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50">
                            Download and install {{ $manifest->version }}
                        </button>

                        <p class="text-xs text-slate-500">
                            Your database, uploads, <code>.env</code>, installed themes and page designs are never
                            touched by an update.
                        </p>
                    </form>
                </x-admin.card>
            @endif
        </div>

        <div class="space-y-5">
            <x-admin.card title="Backups" description="Kept on the private disk, never reachable by URL.">
                <form method="POST" action="{{ route('admin.updates.backup') }}" class="mb-3">
                    @csrf
                    <button class="w-full rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                        Back up the database now
                    </button>
                </form>

                @if ($backups)
                    <ul class="divide-y divide-slate-100 text-sm">
                        @foreach ($backups as $backup)
                            <li class="flex items-center justify-between gap-2 py-2">
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-medium text-slate-700">{{ $backup['name'] }}</span>
                                    <span class="text-[11px] text-slate-400">
                                        {{ number_format($backup['size'] / 1048576, 2) }} MB ·
                                        {{ $backup['created_at']->diffForHumans() }}
                                    </span>
                                </span>
                                <span class="flex shrink-0 gap-1">
                                    <a href="{{ route('admin.updates.backups.download', $backup['name']) }}"
                                       class="rounded border border-slate-300 px-2 py-1 text-[11px] hover:bg-slate-50">Download</a>
                                    <form method="POST" action="{{ route('admin.updates.backups.destroy', $backup['name']) }}"
                                          onsubmit="return confirm('Delete this backup permanently?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded border border-rose-300 px-2 py-1 text-[11px] text-rose-600">Delete</button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-sm text-slate-500">No backups yet.</p>
                @endif
            </x-admin.card>

            <x-admin.card title="What an update changes">
                <dl class="space-y-3 text-xs">
                    <div>
                        <dt class="font-semibold text-slate-700">Replaced</dt>
                        <dd class="text-slate-500">Program code, the bundled theme, and the built assets.</dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700">Never touched</dt>
                        <dd class="text-slate-500">
                            Your database, uploaded files, paid downloads, <code>.env</code>, themes you installed,
                            and everything you built with the visual editor.
                        </dd>
                    </div>
                    <div>
                        <dt class="font-semibold text-slate-700">Checked first</dt>
                        <dd class="text-slate-500">
                            The download is verified against the checksum the release publishes. If it does not
                            match, nothing is installed.
                        </dd>
                    </div>
                </dl>

                @if ($manifestUrl)
                    <p class="mt-4 break-all border-t border-slate-100 pt-3 font-mono text-[10px] text-slate-400">
                        {{ $manifestUrl }}
                    </p>
                @endif
            </x-admin.card>
        </div>
    </div>
@endsection
