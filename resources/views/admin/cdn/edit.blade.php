@extends('admin.layout')
@section('title', $definition['name'])
@section('subtitle', 'Media storage provider')

@section('content')
    @php
        $offloads = ($definition['kind'] ?? 'proxy') !== 'proxy';
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route('admin.cdn.update', $connection->provider) }}" class="lg:col-span-2">
            @csrf @method('PUT')

            <x-admin.card title="Connection"
                          description="Stored encrypted. Leave a field blank to keep the value already saved.">
                <div class="space-y-5">
                    @foreach ($fields as $key => $field)
                        @php $type = $field['type'] ?? 'text'; @endphp

                        @if ($type === 'select')
                            <x-form.field :label="$field['label']" :name="'credentials.'.$key" :help="$field['help'] ?? null">
                                <x-form.select :name="'credentials['.$key.']'"
                                               :options="$field['options'] ?? []"
                                               :value="$connection->credential($key, array_key_first($field['options'] ?? []))" />
                            </x-form.field>
                        @else
                            <x-form.field :label="$field['label']" :name="'credentials.'.$key"
                                          :required="! ($field['optional'] ?? false)"
                                          :help="$field['help'] ?? ($type === 'secret' ? 'Encrypted at rest and never shown again.' : null)">
                                <input type="{{ $type === 'secret' ? 'password' : 'text' }}"
                                       name="credentials[{{ $key }}]"
                                       id="credentials.{{ $key }}"
                                       autocomplete="new-password"
                                       value="{{ $type === 'secret' ? '' : old('credentials.'.$key, $connection->credential($key)) }}"
                                       placeholder="{{ $filled[$key] ? ($type === 'secret' ? '••••••••  (saved)' : '') : 'Not set' }}"
                                       class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            </x-form.field>
                        @endif
                    @endforeach
                </div>
            </x-admin.card>

            @if ($offloads)
                <x-admin.card title="Behaviour" class="mt-6">
                    <div class="space-y-5">
                        <x-form.toggle name="keep_local"
                                       label="Keep a copy of every file on this server"
                                       :checked="$connection->keep_local"
                                       help="Recommended. Uploads go to the provider and stay here too, so losing the bucket cannot lose your media and switching provider is a single click. Turn it off only if you are offloading to free up disk space - then that bucket holds your only copy, and backing it up is on you." />

                        <x-form.field label="Folder prefix" name="path_prefix"
                                      help="Optional. Everything is stored under this folder, which lets several sites share one bucket without colliding. Changing it after files have been uploaded leaves them where they were - move them yourself, or upload them again.">
                            <x-form.input name="path_prefix" :value="$connection->path_prefix" placeholder="e.g. example-com/media" />
                        </x-form.field>
                    </div>

                    <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save
                        </button>
                        <a href="{{ route('admin.cdn.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Back</a>
                    </div>
                </x-admin.card>
            @else
                <x-admin.card class="mt-6">
                    <div class="flex flex-wrap gap-2">
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            Save
                        </button>
                        <a href="{{ route('admin.cdn.index') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Back</a>
                    </div>
                </x-admin.card>
            @endif
        </form>

        <div class="space-y-6">
            <x-admin.card title="What this provider does">
                <p class="text-sm text-slate-600">
                    {{ $definition['description'] ?? 'Files are uploaded here and served from it.' }}
                </p>

                @if (! empty($definition['setup']))
                    <p class="mt-3 text-xs text-slate-500">{{ $definition['setup'] }}</p>
                @endif

                @if ($offloads)
                    <p class="mt-3 text-xs text-slate-500">
                        The bucket has to allow public reads. Radius uploads without an access-control
                        header on purpose - modern S3 buckets reject one, and R2 and Google never
                        supported it - so who may read a file is set on the bucket, not per upload.
                    </p>
                @endif
            </x-admin.card>

            <x-admin.card title="Status">
                <div class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-600">Credentials</span>
                        @if ($connection->isConfigured())
                            <x-admin.badge color="green">Complete</x-admin.badge>
                        @else
                            <x-admin.badge color="amber">{{ count($connection->missingFields()) }} missing</x-admin.badge>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-600">In use</span>
                        <x-admin.badge :color="$connection->is_enabled ? 'green' : 'gray'">
                            {{ $connection->is_enabled ? 'Yes' : 'No' }}
                        </x-admin.badge>
                    </div>

                    @if ($connection->deliveryUrl())
                        <div>
                            <span class="text-slate-600">Files will be served from</span>
                            <code class="mt-1 block break-all rounded-lg bg-slate-900 p-2 text-[11px] text-slate-100">{{ $connection->deliveryUrl() }}/</code>
                        </div>
                    @endif
                </div>

                @if ($connection->missingFields())
                    <p class="mt-4 text-xs text-slate-500">
                        Still empty: {{ implode(', ', $connection->missingFields()) }}.
                    </p>
                @endif

                <div class="mt-5 flex flex-wrap gap-2 border-t border-slate-100 pt-5">
                    @if ($offloads)
                        <form method="POST" action="{{ route('admin.cdn.test', $connection->provider) }}">
                            @csrf
                            <button type="submit" @disabled(! $connection->isConfigured())
                                    class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-400">
                                Test connection
                            </button>
                        </form>
                    @endif

                    @if ($connection->is_enabled)
                        <form method="POST" action="{{ route('admin.cdn.disable', $connection->provider) }}">
                            @csrf
                            <button type="submit" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                                Switch off
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.cdn.enable', $connection->provider) }}">
                            @csrf
                            <button type="submit" @disabled(! $connection->isConfigured())
                                    class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                                Use this provider
                            </button>
                        </form>
                    @endif
                </div>

                @if ($offloads)
                    <p class="mt-3 text-xs text-slate-500">
                        Testing writes a small text file, reads it back and deletes it. Worth doing before
                        switching over, rather than finding out from a library full of broken images.
                    </p>
                @endif
            </x-admin.card>

            @if ($offloads && $progress['pending'] && $connection->is_enabled)
                <x-admin.card title="Files waiting">
                    <p class="text-sm text-slate-600">
                        {{ number_format($progress['pending']) }} file(s) uploaded before you switched this on
                        are still only on this server. They are being served from here, so nothing is broken -
                        move them over from the
                        <a href="{{ route('admin.cdn.index') }}" class="font-medium text-indigo-600 hover:underline">storage screen</a>.
                    </p>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
