@extends('installer.layout', ['step' => 'site'])
@section('title', 'Site details')
@section('description', 'All of this can be changed later from the admin panel.')

@section('content')
    <form method="POST" action="{{ route('install.site.save') }}" class="space-y-5">
        @csrf

        <x-form.field label="Site name" name="site_name" required>
            <x-form.input name="site_name" :value="$values['site_name']" required autofocus />
        </x-form.field>

        <x-form.field label="Contact email" name="site_email" required
                      help="Used as the sender address and for contact form notifications.">
            <x-form.input name="site_email" type="email" :value="$values['site_email']" required />
        </x-form.field>

        <x-form.field label="Site URL" name="app_url" required
                      help="The address visitors will use, with no trailing slash.">
            <x-form.input name="app_url" type="url" :value="$values['app_url']" required />
        </x-form.field>

        <div class="grid gap-5 sm:grid-cols-2">
            <x-form.field label="Timezone" name="timezone" required>
                <select name="timezone" required class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($timezones as $zone)
                        <option value="{{ $zone }}" @selected($values['timezone'] === $zone)>{{ $zone }}</option>
                    @endforeach
                </select>
            </x-form.field>

            <x-form.field label="Currency" name="currency" required>
                <x-form.select name="currency" :value="$values['currency']" :options="$currencies" />
            </x-form.field>
        </div>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Continue
            </button>
            <a href="{{ route('install.database') }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">Back</a>
        </div>
    </form>
@endsection
