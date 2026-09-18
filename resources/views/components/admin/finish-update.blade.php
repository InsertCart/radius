{{-- Shown when the files on disk are ahead of the database: a release copied
     in by FTP or a file manager, which runs no migrations. Editors are told to
     fetch an administrator, because only an admin may run it. --}}
@php
    $state = app(\App\Cms\Updates\UpgradeState::class);
    $needed = $state->needsFinishing();
@endphp

@if ($needed)
    <div class="mb-6 rounded-xl border border-amber-300 bg-amber-50 p-4">
        <h2 class="text-sm font-semibold text-amber-900">This update is not finished</h2>

        <p class="mt-1 text-sm text-amber-800">
            The files on this site are from version {{ cms_version() }}, but the database is still set up for
            {{ $state->recordedVersion() ?? 'an earlier version' }}. Pages can fail until the database catches up.
            @if (count($pending = $state->pendingMigrations()))
                {{ count($pending) }} database {{ \Illuminate\Support\Str::plural('change', count($pending)) }} waiting.
            @endif
        </p>

        @admin
            <form method="POST" action="{{ route('admin.updates.finish') }}" class="mt-3">
                @csrf
                <button type="submit"
                        class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                    Finish the update
                </button>
                <span class="ml-2 text-xs text-amber-700">Takes a database backup first.</span>
            </form>
        @else
            <p class="mt-3 text-sm text-amber-800">Ask an administrator to finish it from Updates.</p>
        @endadmin
    </div>
@endif
