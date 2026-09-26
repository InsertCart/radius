@extends('admin.layout')
@section('title', 'Plugins')
@section('subtitle', 'Add-ons installed separately from the CMS. A plugin runs only while it is switched on.')

@section('content')
    @if ($safeMode)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <strong>Safe mode is on.</strong> CMS_PLUGINS_SAFE_MODE is set, so no plugin is loaded, whatever
            its switch says. Remove it from .env once the plugin that caused trouble is off or uninstalled.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            @forelse ($plugins as $plugin)
                <div @class([
                    'rounded-2xl border bg-white p-5 shadow-sm',
                    'border-indigo-200 ring-1 ring-indigo-100' => $plugin->enabled,
                    'border-slate-200' => ! $plugin->enabled,
                ])>
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h3 class="flex flex-wrap items-center gap-2 font-semibold text-slate-900">
                                {{ $plugin->name }}
                                <span class="text-xs font-normal text-slate-500">v{{ $plugin->version }}</span>
                                @if ($plugin->enabled)
                                    <x-admin.badge color="green">On</x-admin.badge>
                                @endif
                                @isset($updates[$plugin->slug])
                                    <x-admin.badge color="amber">Update {{ $updates[$plugin->slug]->version }}</x-admin.badge>
                                @endisset
                            </h3>
                            @if ($plugin->author)
                                <p class="text-xs text-slate-500">
                                    by
                                    @if ($plugin->author_url)
                                        <a href="{{ $plugin->author_url }}" target="_blank" rel="noopener" class="underline hover:text-slate-700">{{ $plugin->author }}</a>
                                    @else
                                        {{ $plugin->author }}
                                    @endif
                                </p>
                            @endif
                            @if ($plugin->description)
                                <p class="mt-2 text-sm text-slate-600">{{ $plugin->description }}</p>
                            @endif
                            @unless ($plugin->existsOnDisk())
                                <p class="mt-2 text-xs text-rose-600">The files for this plugin are missing from the plugins folder.</p>
                            @endunless
                        </div>

                        <form method="POST" action="{{ route('admin.plugins.toggle', $plugin->slug) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    @class([
                                        'relative inline-flex h-6 w-11 shrink-0 rounded-full transition',
                                        'bg-indigo-600' => $plugin->enabled,
                                        'bg-slate-300' => ! $plugin->enabled,
                                    ])
                                    title="{{ $plugin->enabled ? 'Turn off' : 'Turn on' }}">
                                <span @class([
                                    'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition',
                                    'left-[22px]' => $plugin->enabled,
                                    'left-0.5' => ! $plugin->enabled,
                                ])></span>
                            </button>
                        </form>
                    </div>

                    @hook('admin.plugins.card', $plugin)

                    <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3">
                        @isset($updates[$plugin->slug])
                            <form method="POST" action="{{ route('admin.plugins.marketplace.install', $updates[$plugin->slug]->slug) }}"
                                  onsubmit="this.querySelector('button').disabled = true; this.querySelector('button').textContent = 'Updating…';">
                                @csrf
                                <button class="rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-medium text-white hover:bg-amber-600">
                                    Update to {{ $updates[$plugin->slug]->version }}
                                </button>
                            </form>
                        @endisset

                        @if ($plugin->enabled && $plugin->settingsRoute())
                            <a href="{{ route($plugin->settingsRoute()) }}"
                               class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-indigo-700">
                                Settings
                            </a>
                        @endif

                        @unless ($plugin->enabled)
                            <form method="POST" action="{{ route('admin.plugins.destroy', $plugin->slug) }}"
                                  onsubmit="return confirm('Uninstall {{ $plugin->name }}? Its files, its settings and any data it stored are removed permanently.')">
                                @csrf
                                @method('DELETE')
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50">
                                    Uninstall
                                </button>
                            </form>
                        @endunless

                        <span class="ml-auto font-mono text-[11px] text-slate-400">plugins/{{ $plugin->slug }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
                    <x-admin.icon name="plugins" class="mx-auto h-8 w-8 text-slate-400" />
                    <p class="mt-3 font-medium text-slate-700">No plugins installed</p>
                    <p class="mt-1 text-sm text-slate-500">Upload a plugin ZIP, or copy a plugin folder into <code>plugins/</code> on the server.</p>
                </div>
            @endforelse

            @foreach ($invalid as $folder => $reason)
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700">
                    <strong class="font-mono">plugins/{{ $folder }}</strong> was not loaded: {{ $reason }}
                </div>
            @endforeach
        </div>

        <div class="space-y-6">
            @if ($directoryEnabled)
                <x-admin.card title="Find a plugin" description="Add-ons from the plugin directory">
                    <a href="{{ route('admin.plugins.marketplace.index') }}"
                       class="block w-full rounded-lg bg-indigo-600 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-indigo-700">
                        Browse plugins
                    </a>
                </x-admin.card>
            @endif

            @if ($uploadsAllowed)
                <x-admin.card title="Upload a plugin" description="A .zip archive with plugin.json at its root">
                    <form method="POST" action="{{ route('admin.plugins.upload') }}" enctype="multipart/form-data" class="space-y-4">
                        @csrf

                        <x-form.field label="Plugin archive" name="plugin" required>
                            <input type="file" name="plugin" accept=".zip" required
                                   class="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100">
                        </x-form.field>

                        <x-form.toggle name="overwrite" label="Replace an installed copy (update)" />

                        <button type="submit" class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Upload and install
                        </button>
                    </form>

                    <div class="mt-4 space-y-2 border-t border-slate-100 pt-4 text-xs text-slate-500">
                        <p><strong class="text-slate-700">Maximum size:</strong> {{ number_format($maxUploadKb / 1024) }} MB</p>
                        <p>
                            A plugin is PHP code and runs with the same access as the CMS itself.
                            Only install plugins from people you trust. A new plugin arrives switched off.
                        </p>
                    </div>
                </x-admin.card>
            @else
                <x-admin.card title="Uploads are off" description="CMS_PLUGIN_UPLOADS is set to false">
                    <p class="text-sm text-slate-500">
                        Plugins can only be installed by copying their folder into <code>plugins/</code> on the server.
                    </p>
                </x-admin.card>
            @endif

            <x-admin.card title="Plugin structure">
<pre class="overflow-x-auto rounded-lg bg-slate-900 p-3 text-[11px] leading-relaxed text-slate-100">my-plugin/
├── plugin.json
├── src/
│   └── MyPluginServiceProvider.php
├── routes/              optional
├── resources/views/     optional
├── database/migrations/ optional
└── assets/              optional, published</pre>
                <p class="mt-3 text-xs text-slate-500">
                    Switching a plugin off keeps its settings and data. Uninstalling runs its clean-up,
                    rolls back its tables and deletes its folder. CMS updates never touch the plugins folder.
                </p>
            </x-admin.card>
        </div>
    </div>
@endsection
