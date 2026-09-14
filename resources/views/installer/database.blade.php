@extends('installer.layout', ['step' => 'database'])
@section('title', 'Database connection')
@section('description', 'Create an empty database first, then enter its details here. We test the connection before saving anything.')

@section('content')
    <form method="POST" action="{{ route('install.database.save') }}" class="space-y-5">
        @csrf

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field label="Host" name="host" required>
                <x-form.input name="host" :value="$values['host']" required />
            </x-form.field>

            <x-form.field label="Port" name="port" required>
                <x-form.input name="port" :value="$values['port']" required />
            </x-form.field>
        </div>

        <x-form.field label="Database name" name="database" required>
            <x-form.input name="database" :value="$values['database']" required autofocus />
        </x-form.field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field label="Username" name="username" required>
                <x-form.input name="username" :value="$values['username']" required />
            </x-form.field>

            <x-form.field label="Password" name="password" help="Leave blank if there is no password.">
                <x-form.input name="password" type="password" autocomplete="off" />
            </x-form.field>
        </div>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Test and continue
            </button>
            <a href="{{ route('install.requirements') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">
                Back
            </a>
        </div>
    </form>
@endsection
