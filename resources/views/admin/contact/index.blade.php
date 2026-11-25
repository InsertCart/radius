@extends('admin.layout')
@section('title', 'Messages')
@section('subtitle', $unreadCount.' unread')

@section('content')
    <x-admin.card bodyClass="" x-data="{ selected: [] }">
        <form method="GET" class="flex flex-wrap gap-2 border-b border-slate-100 p-4">
            <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search messages"
                   class="min-w-[12rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <label class="flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="checkbox" name="unread" value="1" @checked($filters['unread'] ?? false)
                       class="h-4 w-4 rounded border-slate-300 text-indigo-600">
                Unread only
            </label>
            <button class="rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50">Filter</button>
            @if (auth()->user()->isAdmin())
                <a class="self-center px-2 text-sm text-indigo-600 hover:underline" href="{{ route('admin.settings.edit', 'advanced') }}#contact_math_enabled">Spam protection settings</a>
            @endif
        </form>

        @if ($submissions->isEmpty())
            <x-admin.empty message="No messages yet." />
        @else
            @php($pageIds = $submissions->pluck('id')->map(fn ($id) => (string) $id)->values())
            <form id="bulk-delete-messages" method="POST" action="{{ route('admin.contact.bulk-destroy') }}"
                  onsubmit="return confirm('Permanently delete the selected messages?')"
                  class="flex flex-wrap items-center gap-4 border-b border-slate-100 p-4">
                @csrf @method('DELETE')
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" :checked="selected.length === {{ $submissions->count() }}"
                           x-effect="$el.indeterminate = selected.length > 0 && selected.length < {{ $submissions->count() }}"
                           @change="selected = $el.checked ? @js($pageIds) : []">
                    Select all on this page
                </label>
                <button type="submit" disabled :disabled="selected.length === 0"
                        class="rounded-lg border border-rose-600 px-3 py-2 text-sm text-rose-600 disabled:opacity-50">
                    Delete selected <span x-show="selected.length" x-cloak x-text="'(' + selected.length + ')'"></span>
                </button>
            </form>
            <ul class="divide-y divide-slate-100">
                @foreach ($submissions as $submission)
                    <li class="flex items-start">
                        <input type="checkbox" name="ids[]" value="{{ $submission->id }}" form="bulk-delete-messages"
                               x-model="selected" class="ml-4 mt-5" aria-label="Select message {{ $submission->id }} from {{ $submission->email }}">
                        <a href="{{ route('admin.contact.show', $submission) }}"
                           class="flex min-w-0 flex-1 items-start gap-3 p-4 hover:bg-slate-50">
                            <span @class([
                                'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                                'bg-indigo-500' => ! $submission->read_at,
                                'bg-transparent' => $submission->read_at,
                            ])></span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p @class(['truncate text-sm', 'font-semibold text-slate-900' => ! $submission->read_at, 'text-slate-700' => $submission->read_at])>
                                        {{ $submission->name }}
                                        <span class="font-normal text-slate-400">&lt;{{ $submission->email }}&gt;</span>
                                    </p>
                                    <span class="shrink-0 text-xs text-slate-400">{{ $submission->created_at->diffForHumans() }}</span>
                                </div>
                                <p class="truncate text-sm text-slate-600">{{ $submission->subject ?: 'No subject' }}</p>
                                <p class="truncate text-xs text-slate-400">{{ $submission->message }}</p>
                            </div>
                        </a>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4">{{ $submissions->links() }}</div>
        @endif
    </x-admin.card>
@endsection
