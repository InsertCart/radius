@extends('installer.layout', ['step' => 'finish'])
@section('title', 'Installation complete')

@section('content')
    <div class="space-y-6">
        <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-4">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full bg-emerald-600 text-white">&check;</span>
            <div class="text-sm text-emerald-900">
                <p class="font-semibold">Your site is ready.</p>
                <p class="mt-0.5">Sign in with <strong>{{ $email }}</strong> and the password you just chose.</p>
            </div>
        </div>

        <div>
            <h3 class="text-sm font-semibold text-slate-700">Recommended next steps</h3>
            <ol class="mt-2 space-y-2 text-sm text-slate-600">
                <li class="flex gap-2"><span class="text-slate-400">1.</span> Turn on two-factor authentication for your account.</li>
                <li class="flex gap-2"><span class="text-slate-400">2.</span> Switch off any modules you do not need, under System &rarr; Modules.</li>
                <li class="flex gap-2"><span class="text-slate-400">3.</span> Set your email provider under Settings &rarr; Email, then send a test.</li>
                <li class="flex gap-2"><span class="text-slate-400">4.</span> Configure a payment gateway if you plan to sell.</li>
                <li class="flex gap-2"><span class="text-slate-400">5.</span> Change <code class="rounded bg-slate-100 px-1">CMS_ADMIN_PREFIX</code> in .env to move the admin panel off /admin.</li>
            </ol>
        </div>

        {{-- Stating what has already happened, not asking for work. The
             wizard closed itself the moment setup finished; telling the owner
             to "delete the install routes" implies otherwise and is the kind
             of instruction people either ignore or act on destructively. --}}
        <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
            <svg class="mt-0.5 h-5 w-5 shrink-0 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                <rect x="4" y="10" width="16" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            </svg>
            <div>
                <p class="font-semibold text-slate-800">Setup is now closed</p>
                <p class="mt-0.5">
                    The wizard has locked itself and will not run again, so nobody can point this
                    site at another database. There is nothing for you to delete.
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-5">
            <a href="{{ $adminUrl }}" class="rounded-lg bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                Go to the admin panel
            </a>
            <a href="{{ $siteUrl }}" class="rounded-lg border border-slate-300 px-5 py-2.5 text-sm hover:bg-slate-50">
                View the site
            </a>
        </div>
    </div>
@endsection
