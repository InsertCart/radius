<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Customer registration. The whole flow can be switched off from the admin
 * panel for sites that only ever have staff accounts.
 */
class RegisterController extends Controller
{
    public function show(): View
    {
        abort_unless(setting('registration_enabled', true), 404);

        return view(theme_view('auth.register', 'auth.register'));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(setting('registration_enabled', true), 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            // Registration never grants staff access; roles are assigned in
            // the admin panel only.
            'role' => User::ROLE_CUSTOMER,
            'status' => 'active',
            'email_verified_at' => setting('email_verification', false) ? null : now(),
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('auth.two_factor_confirmed', true);

        LoginController::afterLogin($request, $user);

        return redirect()->route('account.dashboard')->with('status', 'Welcome aboard.');
    }
}
