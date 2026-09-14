@extends('admin.layout')
@section('title', 'Settings')
@section('subtitle', $definition['label'] ?? '')

@section('content')
    <div class="grid gap-6 lg:grid-cols-4">
        <nav class="lg:col-span-1">
            <ul class="space-y-1">
                @foreach ($groups as $key => $group)
                    <li>
                        <a href="{{ route('admin.settings.edit', $key) }}"
                           @class([
                               'block rounded-lg px-3 py-2 text-sm transition',
                               'bg-indigo-600 text-white' => $key === $activeGroup,
                               'text-slate-600 hover:bg-white hover:text-slate-900' => $key !== $activeGroup,
                           ])>
                            {{ $group['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="lg:col-span-3">
            <form method="POST" action="{{ route('admin.settings.update', $activeGroup) }}" x-data="{
                values: @js(collect($definition['fields'])->mapWithKeys(fn ($f, $k) => [$k => old($k, $values[$k] ?? ($f['default'] ?? ''))])->all()),
            }">
                @csrf
                @method('PUT')

                <x-admin.card :title="$definition['label']">
                    @if ($mailStatus && ! $mailStatus['available'])
                        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            <p class="font-semibold">{{ ucfirst($mailStatus['driver']) }} is not installed on this server.</p>
                            @if ($mailStatus['install_hint'])
                                <p class="mt-1">Run <code class="rounded bg-amber-100 px-1">{{ $mailStatus['install_hint'] }}</code> to enable it. Until then, mail falls back to the log.</p>
                            @endif
                        </div>
                    @endif

                    @if ($mailStatus && $mailStatus['env'])
                        <div class="mb-5 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                            <p class="font-medium text-slate-700">Environment keys for {{ ucfirst($mailStatus['driver']) }}</p>
                            <ul class="mt-2 space-y-1">
                                @foreach ($mailStatus['env'] as $key => $present)
                                    <li class="flex items-center gap-2 text-xs">
                                        <span @class(['h-1.5 w-1.5 rounded-full', 'bg-emerald-500' => $present, 'bg-rose-500' => ! $present])></span>
                                        <code>{{ $key }}</code>
                                        <span class="{{ $present ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $present ? 'set' : 'missing from .env' }}
                                        </span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="space-y-5">
                        @foreach ($definition['fields'] as $key => $field)
                            @php
                                $type = $field['type'] ?? 'text';
                                $value = $values[$key] ?? ($field['default'] ?? null);
                                $depends = $field['depends'] ?? null;
                            @endphp

                            <div @if ($depends)
                                    x-show="@foreach ($depends as $dk => $dv) values['{{ $dk }}'] === @js($dv) @endforeach"
                                    x-cloak
                                 @endif>
                                @include('admin.settings.field', compact('key', 'field', 'type', 'value'))
                            </div>
                        @endforeach
                    </div>
                </x-admin.card>

                <div class="mt-4 flex justify-end">
                    <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                        Save settings
                    </button>
                </div>
            </form>

            @if ($activeGroup === 'mail')
                <x-admin.card title="Send a test email" description="Confirm your settings work before relying on them" class="mt-6">
                    <form method="POST" action="{{ route('admin.tools.mail.test') }}" class="flex flex-wrap gap-3">
                        @csrf
                        <input type="email" name="email" required value="{{ auth()->user()->email }}"
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Send test
                        </button>
                    </form>
                </x-admin.card>
            @endif

            @if ($activeGroup === 'sms')
                <x-admin.card title="Send a test SMS" class="mt-6">
                    <form method="POST" action="{{ route('admin.tools.sms.test') }}" class="flex flex-wrap gap-3">
                        @csrf
                        <input type="text" name="phone" required placeholder="+911234567890"
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Send test
                        </button>
                    </form>
                </x-admin.card>
            @endif

            @if ($activeGroup === 'firebase')
                <x-admin.card title="Send a test notification" description="Goes to every registered device" class="mt-6">
                    <form method="POST" action="{{ route('admin.tools.push.test') }}">
                        @csrf
                        <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">
                            Send test push
                        </button>
                    </form>
                </x-admin.card>
            @endif
        </div>
    </div>
@endsection
