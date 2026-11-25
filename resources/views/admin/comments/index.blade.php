@extends('admin.layout')
@section('title', 'Comments')

@section('content')
    <div class="mb-4 flex flex-wrap gap-2">
        @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'spam' => 'Spam', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.comments.index', ['status' => $key]) }}"
               @class([
                   'rounded-lg px-4 py-2 text-sm font-medium transition',
                   'bg-indigo-600 text-white' => $status === $key,
                   'bg-white text-slate-600 hover:bg-slate-50' => $status !== $key,
               ])>
                {{ $label }}
                @if (($counts[$key] ?? 0) > 0)
                    <span class="ml-1 text-xs opacity-75">{{ $counts[$key] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    <x-admin.card bodyClass="" x-data="{ selected: [] }">
        @if ($comments->isEmpty())
            <x-admin.empty message="Nothing to moderate here." />
        @else
            @php($pageIds = $comments->pluck('id')->map(fn ($id) => (string) $id)->values())
            <form id="bulk-delete-comments" method="POST" action="{{ route('admin.comments.bulk-destroy') }}"
                  onsubmit="return confirm('Permanently delete the selected comments and all their replies?')"
                  class="flex flex-wrap items-center gap-4 border-b border-slate-100 p-4">
                @csrf @method('DELETE')
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" :checked="selected.length === {{ $comments->count() }}"
                           x-effect="$el.indeterminate = selected.length > 0 && selected.length < {{ $comments->count() }}"
                           @change="selected = $el.checked ? @js($pageIds) : []">
                    Select all on this page
                </label>
                <button type="submit" disabled :disabled="selected.length === 0"
                        class="rounded-lg border border-rose-600 px-3 py-2 text-sm text-rose-600 disabled:opacity-50">
                    Delete selected <span x-show="selected.length" x-cloak x-text="'(' + selected.length + ')'"></span>
                </button>
            </form>
            <ul class="divide-y divide-slate-100">
                @foreach ($comments as $comment)
                    <li class="p-4">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <input type="checkbox" name="ids[]" value="{{ $comment->id }}" form="bulk-delete-comments"
                                   x-model="selected" aria-label="Select comment {{ $comment->id }} by {{ $comment->displayName() }}">
                            <div class="min-w-0 flex-1">
                                <p class="text-sm">
                                    <span class="font-medium text-slate-900">{{ $comment->displayName() }}</span>
                                    <span class="text-slate-400">&lt;{{ $comment->author_email }}&gt;</span>
                                    <span class="text-xs text-slate-400">&middot; {{ $comment->created_at->diffForHumans() }}</span>
                                </p>
                                <p class="mt-1 text-sm text-slate-700">{{ $comment->body }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    @if ($comment->post)
                                        on <a href="{{ route('admin.posts.edit', $comment->post) }}" class="hover:text-indigo-600">{{ $comment->post->title }}</a>
                                    @else
                                        on a deleted post
                                    @endif
                                    @if ($comment->ip_address) &middot; {{ $comment->ip_address }} @endif
                                </p>
                                @if (auth()->user()->isAdmin())
                                    @php($bodyError = $errors->getBag('comment'.$comment->id)->first('body'))
                                    <details class="mt-2" @if ($bodyError) open @endif>
                                        <summary class="cursor-pointer text-xs text-indigo-600">Edit comment</summary>
                                        <form method="POST" action="{{ route('admin.comments.update-body', $comment) }}" class="mt-2">
                                            @csrf @method('PATCH')
                                            <label for="body-{{ $comment->id }}" class="text-sm font-medium">Comment text</label>
                                            <textarea id="body-{{ $comment->id }}" name="body" rows="4" required minlength="3" maxlength="3000"
                                                      class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $bodyError ? (is_string(old('body')) ? old('body') : '') : $comment->body }}</textarea>
                                            @if ($bodyError)
                                                <p class="mt-1 text-sm text-rose-600">{{ $bodyError }}</p>
                                            @endif
                                            <button class="mt-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm text-white">Save comment</button>
                                        </form>
                                    </details>
                                @endif
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <x-admin.badge :color="match($comment->status) { 'approved' => 'green', 'spam' => 'red', default => 'amber' }">
                                    {{ ucfirst($comment->status) }}
                                </x-admin.badge>

                                @foreach (['approved' => 'Approve', 'pending' => 'Unapprove', 'spam' => 'Spam'] as $value => $label)
                                    @continue($comment->status === $value)
                                    <form method="POST" action="{{ route('admin.comments.update', $comment) }}">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="status" value="{{ $value }}">
                                        <button class="text-xs text-slate-500 hover:text-indigo-600">{{ $label }}</button>
                                    </form>
                                @endforeach

                                <form method="POST" action="{{ route('admin.comments.destroy', $comment) }}"
                                      onsubmit="return confirm('Permanently delete this comment and all its replies?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-rose-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
            <div class="border-t border-slate-100 p-4">{{ $comments->links() }}</div>
        @endif
    </x-admin.card>
@endsection
