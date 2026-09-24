@extends('admin.layout')
@section('title', $upload['kind'] === 'wordpress' ? 'Review WordPress import' : 'Review import')
@section('subtitle', $upload['original_name'])

@php
    $isWordPress = $upload['kind'] === 'wordpress';
    $found = $analysis['types'] ?? [];
    $site = $analysis['site'] ?? ($analysis['manifest']['site'] ?? []);
    $hasFiles = $isWordPress ? false : ($analysis['media'] ?? false);
@endphp

@section('content')
    <div class="space-y-6">

        <x-admin.card title="What this file holds">
            @if ($found === [])
                <p class="text-sm text-slate-600">
                    Nothing this site can import was found in that file.
                    @if (! empty($analysis['unknown']))
                        It carries {{ implode(', ', $analysis['unknown']) }}, which this version does not read.
                    @endif
                </p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($found as $key => $count)
                        <span class="rounded-lg bg-slate-100 px-3 py-1.5 text-sm text-slate-700">
                            <span class="font-semibold text-slate-900">{{ number_format($count) }}</span>
                            {{ str_replace('_', ' ', $key) }}
                        </span>
                    @endforeach
                </div>
            @endif

            <dl class="mt-5 grid gap-4 border-t border-slate-100 pt-4 text-sm sm:grid-cols-3">
                @if (! empty($site['title']) || ! empty($site['name']))
                    <div>
                        <dt class="text-xs text-slate-500">From</dt>
                        <dd class="font-medium text-slate-800">{{ $site['title'] ?? $site['name'] }}</dd>
                    </div>
                @endif
                @if (! empty($site['url']))
                    <div>
                        <dt class="text-xs text-slate-500">Old address</dt>
                        <dd class="truncate font-medium text-slate-800">{{ $site['url'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt class="text-xs text-slate-500">Image files</dt>
                    <dd class="font-medium text-slate-800">
                        {{ $hasFiles ? 'Carried in the file' : 'Not in the file' }}
                    </dd>
                </div>
            </dl>

            @if (! empty($analysis['unknown']) && $found !== [])
                <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This file also holds {{ implode(', ', $analysis['unknown']) }}, which this version does not import. It will be left alone.
                </p>
            @endif
        </x-admin.card>

        @if ($found !== [])
            <form method="POST" action="{{ route('admin.transfer.run', $token) }}" class="space-y-6">
                @csrf

                <x-admin.card title="What to bring in">
                    <div class="grid gap-3 sm:grid-cols-2">
                        @foreach ($found as $key => $count)
                            @php $resource = $resources[$key] ?? null; @endphp
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="checkbox" name="types[]" value="{{ $key }}" checked
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-slate-900">
                                        {{ $resource?->label() ?? ucfirst(str_replace('_', ' ', $key)) }}
                                        <span class="font-normal text-slate-400">({{ number_format($count) }})</span>
                                    </span>
                                    @if ($resource?->hint())
                                        <span class="mt-0.5 block text-xs text-slate-500">{{ $resource->hint() }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                </x-admin.card>

                <div class="grid gap-6 lg:grid-cols-2">
                    <x-admin.card title="If something is already here">
                        <div class="space-y-3">
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" name="mode" value="skip" checked class="mt-0.5 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Leave it alone</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Matching slugs are skipped and counted. Nothing you already have is touched.</span>
                                </span>
                            </label>
                            <label class="flex cursor-pointer gap-3 rounded-xl border border-slate-200 p-3 hover:bg-slate-50">
                                <input type="radio" name="mode" value="update" class="mt-0.5 text-indigo-600">
                                <span>
                                    <span class="block text-sm font-medium text-slate-900">Overwrite it</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">Matching records are replaced with what the file says. There is no undo.</span>
                                </span>
                            </label>
                        </div>

                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <label class="block text-xs font-medium text-slate-600">Publish state</label>
                            <select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                <option value="keep">Keep what the file says</option>
                                <option value="draft">Bring everything in as a draft</option>
                                <option value="published">Publish everything</option>
                            </select>
                            <p class="mt-1.5 text-xs text-slate-500">Importing as drafts lets you look before anything goes live.</p>
                        </div>
                    </x-admin.card>

                    <x-admin.card title="Images">
                        <div class="space-y-3">
                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="import_media" value="1" checked
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                <span>
                                    Bring images across
                                    <span class="mt-0.5 block text-xs text-slate-500">
                                        {{ $hasFiles ? 'The files are in this bundle and will be added to the media library.' : 'Matched against what this site already has.' }}
                                    </span>
                                </span>
                            </label>

                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="download_media" value="1" @checked($isWordPress || ! $hasFiles)
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                <span>
                                    Fetch missing images from the old site
                                    <span class="mt-0.5 block text-xs text-slate-500">
                                        This site will download each picture from
                                        {{ $site['url'] ?? 'the address in the file' }}. That site has to still be up, and a
                                        large library takes a while.
                                    </span>
                                </span>
                            </label>

                            <label class="flex items-start gap-2 text-sm text-slate-700">
                                <input type="checkbox" name="rewrite_urls" value="1" checked
                                       class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                <span>
                                    Repoint image addresses inside the content
                                    <span class="mt-0.5 block text-xs text-slate-500">Rewrites old addresses in post bodies to this site's copies.</span>
                                </span>
                            </label>

                            @unless ($isWordPress)
                                <label class="flex items-start gap-2 text-sm text-slate-700">
                                    <input type="checkbox" name="include_layouts" value="1" checked
                                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                                    <span>Bring builder layouts across</span>
                                </label>
                            @endunless
                        </div>
                    </x-admin.card>
                </div>

                <x-admin.card title="Authors"
                              description="Writers are matched to accounts here by email address.">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="block text-xs font-medium text-slate-600">Anyone without an account here</label>
                            <select name="author_id" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                @foreach ($staff as $member)
                                    <option value="{{ $member->id }}" @selected($member->is(auth()->user()))>
                                        {{ $member->name }} ({{ $member->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <label class="flex items-start gap-2 self-end pb-2 text-sm text-slate-700">
                            <input type="checkbox" name="create_authors" value="1"
                                   class="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600">
                            <span>
                                Create accounts for missing authors
                                <span class="mt-0.5 block text-xs text-slate-500">
                                    Made as customers with no usable password, so nobody can sign in with one until they
                                    reset it.
                                </span>
                            </span>
                        </label>
                    </div>

                    @if ($isWordPress && ! empty($analysis['authors']))
                        <div class="mt-4 border-t border-slate-100 pt-4">
                            <p class="text-xs font-medium text-slate-600">Writers in this file</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($analysis['authors'] as $author)
                                    <span class="rounded-md bg-slate-100 px-2 py-1 text-xs text-slate-600">
                                        {{ $author['name'] }}{{ $author['email'] ? ' · '.$author['email'] : '' }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </x-admin.card>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <p class="text-xs text-slate-500">
                        Nothing has changed yet. Take a backup first if this site already has content you care about.
                    </p>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('admin.transfer.index') }}"
                           class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">Cancel</a>
                        <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-700">
                            Run the import
                        </button>
                    </div>
                </div>
            </form>
        @else
            <div class="flex justify-end">
                <form method="POST" action="{{ route('admin.transfer.discard', $token) }}">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 hover:bg-slate-50">
                        Discard this upload
                    </button>
                </form>
            </div>
        @endif
    </div>
@endsection
