@extends('admin.layout')
@section('title', $submission->subject ?: 'Message')

@section('content')
    <div class="mx-auto max-w-2xl">
        <x-admin.card>
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                <div>
                    <p class="font-medium text-slate-900">{{ $submission->name }}</p>
                    <p class="text-sm text-slate-500">{{ $submission->email }}</p>
                    @if ($submission->phone)
                        <p class="text-sm text-slate-500">{{ $submission->phone }}</p>
                    @endif
                </div>
                <div class="text-right text-xs text-slate-400">
                    <p>{{ format_date($submission->created_at, 'd M Y, H:i') }}</p>
                    @if ($submission->ip_address)
                        <p>{{ $submission->ip_address }}</p>
                    @endif
                </div>
            </div>

            <div class="py-5">
                <p class="whitespace-pre-line text-sm leading-relaxed text-slate-700">{{ $submission->message }}</p>

                @if (filled($submission->extra))
                    {{-- Answers to the form's own extra fields, labelled as
                         they were when the message was sent. --}}
                    <dl class="mt-5 divide-y divide-slate-100 rounded-lg border border-slate-200 text-sm">
                        @foreach ($submission->extra as $answer)
                            <div class="flex flex-wrap gap-x-4 gap-y-1 px-4 py-2.5">
                                <dt class="w-40 shrink-0 font-medium text-slate-500">{{ $answer['label'] ?? '' }}</dt>
                                <dd class="min-w-0 flex-1 whitespace-pre-line text-slate-700">{{ $answer['value'] ?? '' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>

            <div class="flex flex-wrap gap-2 border-t border-slate-100 pt-4">
                <a href="mailto:{{ $submission->email }}?subject={{ rawurlencode('Re: '.($submission->subject ?: 'Your message')) }}"
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Reply by email
                </a>
                <a href="{{ route('admin.contact.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">
                    Back to messages
                </a>
                <form method="POST" action="{{ route('admin.contact.destroy', $submission) }}" class="ml-auto"
                      onsubmit="return confirm('Delete this message?')">
                    @csrf @method('DELETE')
                    <button class="rounded-lg border border-rose-300 px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">Delete</button>
                </form>
            </div>
        </x-admin.card>
    </div>
@endsection
