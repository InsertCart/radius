@extends('admin.layout')
@section('title', 'Media storage')
@section('subtitle', 'Where your uploads live, and the address visitors fetch them from')

@section('content')
    @php
        $offloading = $active && $active->offloads();
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">

            @if (! $active)
                <x-admin.card title="Serving from this server"
                              description="Every file is stored in this site's own storage folder and served by this web server.">
                    <p class="text-sm text-slate-600">
                        That is a perfectly good arrangement for most sites. Pick a provider below if you
                        want images served from an edge network, if your host caps disk space, or if you
                        run more than one server and they need to share an upload folder.
                    </p>
                </x-admin.card>
            @endif

            <x-admin.card title="Providers"
                          description="One at a time. Configure as many as you like; only the one you switch on is used."
                          bodyClass="divide-y divide-slate-100">
                @foreach ($connections as $slug => $connection)
                    @php
                        $definition = $connection->definition();
                        $isActive = $connection->is_enabled;
                        $configured = $connection->isConfigured();
                    @endphp

                    <div class="flex flex-wrap items-start justify-between gap-4 px-5 py-4 first:pt-1 last:pb-1">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('admin.cdn.edit', $slug) }}" class="text-sm font-semibold text-slate-900 hover:text-indigo-600">
                                    {{ $definition['name'] }}
                                </a>

                                @if ($isActive)
                                    <x-admin.badge color="green">In use</x-admin.badge>
                                @elseif ($configured)
                                    <x-admin.badge color="blue">Ready</x-admin.badge>
                                @else
                                    <x-admin.badge color="gray">Not set up</x-admin.badge>
                                @endif

                                @if (($definition['kind'] ?? 'proxy') === 'proxy')
                                    <x-admin.badge color="indigo">Files stay here</x-admin.badge>
                                @endif
                            </div>

                            <p class="mt-1 text-xs text-slate-500">{{ $definition['tagline'] ?? '' }}</p>
                        </div>

                        <div class="flex shrink-0 items-center gap-2">
                            <a href="{{ route('admin.cdn.edit', $slug) }}"
                               class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                {{ $configured ? 'Edit' : 'Set up' }}
                            </a>

                            @if ($isActive)
                                <form method="POST" action="{{ route('admin.cdn.disable', $slug) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                        Switch off
                                    </button>
                                </form>
                            @elseif ($configured)
                                <form method="POST" action="{{ route('admin.cdn.enable', $slug) }}">
                                    @csrf
                                    <button type="submit"
                                            class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700">
                                        Use this
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </x-admin.card>

            @if ($offloading)
                <x-admin.card title="Moving your library"
                              description="Existing files are not touched when you switch a provider on. Move them when it suits you.">
                    <dl class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                        <div>
                            <dt class="text-xs text-slate-500">Files</dt>
                            <dd class="text-lg font-semibold text-slate-900">{{ number_format($progress['total']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">On {{ $active->name() }}</dt>
                            <dd class="text-lg font-semibold text-slate-900">{{ number_format($progress['offloaded']) }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">Still to upload</dt>
                            <dd class="text-lg font-semibold {{ $progress['pending'] ? 'text-amber-600' : 'text-slate-900' }}">
                                {{ number_format($progress['pending']) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-slate-500">No copy here</dt>
                            <dd class="text-lg font-semibold {{ $progress['remote_only'] ? 'text-amber-600' : 'text-slate-900' }}">
                                {{ number_format($progress['remote_only']) }}
                            </dd>
                        </div>
                    </dl>

                    <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                        <form method="POST" action="{{ route('admin.cdn.push') }}">
                            @csrf
                            <button type="submit" @disabled(! $progress['pending'])
                                    class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                Upload {{ $progress['pending'] ? min($progress['pending'], $batchSize) : 0 }} file(s) to {{ $active->name() }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.cdn.pull') }}">
                            @csrf
                            <button type="submit" @disabled(! $progress['remote_only'])
                                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400">
                                Bring {{ $progress['remote_only'] ? min($progress['remote_only'], $batchSize) : 0 }} file(s) back here
                            </button>
                        </form>
                    </div>

                    <p class="mt-4 text-xs text-slate-500">
                        Each press moves up to {{ $batchSize }} files, so the request always finishes even on a
                        slow shared host. Press it again until nothing is left. On a big library run
                        <code class="rounded bg-slate-100 px-1 py-0.5">php artisan cms:cdn-sync</code> from the
                        command line instead and it will do the lot in one go.
                    </p>
                </x-admin.card>
            @endif
        </div>

        <div class="space-y-6">
            <x-admin.card title="How it is serving now">
                @if (! $active)
                    <p class="text-sm text-slate-600">Directly from this server.</p>
                @else
                    <p class="text-sm text-slate-600">
                        Through <strong>{{ $active->name() }}</strong>, at
                        <code class="break-all text-xs text-slate-800">{{ $active->deliveryUrl() }}</code>.
                    </p>

                    <p class="mt-3 text-xs text-slate-500">
                        @if (! $active->offloads())
                            Your files never leave this server. The CDN asks for each one the first time
                            somebody does, and serves its own copy afterwards. Switch it off and everything
                            carries on working immediately.
                        @elseif ($active->keep_local)
                            A copy of every uploaded file is kept on this server as well, so nothing is lost
                            if the bucket goes away, and switching back is a single click.
                        @else
                            Files are deleted from this server once they reach {{ $active->name() }}, so that
                            bucket holds the only copy. Make sure it is backed up.
                        @endif
                    </p>
                @endif
            </x-admin.card>

            @if ($active && $active->offloads() && $progress['remote_only'])
                <x-admin.card title="One copy only">
                    <p class="text-sm text-slate-600">
                        {{ number_format($progress['remote_only']) }} file(s) exist nowhere but
                        {{ $active->name() }}. Until they are back here, this provider cannot be switched
                        off or swapped - doing so would leave them unreachable with no way back.
                    </p>
                </x-admin.card>
            @endif

            <x-admin.card title="What this does not cover">
                <p class="text-sm text-slate-600">
                    This screen governs the media library: uploads, and the thumbnails made from them.
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    Theme stylesheets, scripts and fonts are served by this site either way. A pull CDN in
                    front of the whole domain covers those too, with nothing to configure here. Paid
                    downloads are deliberately left out: they are served by a controller that checks the
                    order first, and a public bucket would hand them to anyone who guessed the address.
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
