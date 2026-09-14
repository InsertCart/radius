<?php

namespace App\Http\Controllers\Admin;

use App\Cms\Support\TwoFactorService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(private TwoFactorService $twoFactor) {}

    public function index(Request $request): View
    {
        return view('admin.users.index', [
            'users' => User::when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($sub) => $sub->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
                ->when($request->filled('role'), fn ($q) => $q->where('role', $request->string('role')))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'roles' => $this->roles(),
            'filters' => $request->only(['q', 'role']),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.form', [
            'user' => new User(['role' => User::ROLE_CUSTOMER, 'status' => 'active']),
            'roles' => $this->roles(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'status' => ['required', 'in:active,suspended'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = User::create(array_merge($validated, [
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]));

        activity('user.created', "Created the account {$user->email}.", $user);

        return redirect()->route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', [
            'user' => $user,
            'roles' => $this->roles(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'role' => ['required', Rule::in(array_keys($this->roles()))],
            'status' => ['required', 'in:active,suspended'],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        // Guard against an admin demoting or suspending themselves and losing
        // access to the panel entirely.
        if ($user->id === $request->user()->id) {
            $validated['role'] = $user->role;
            $validated['status'] = 'active';
        }

        $user->fill(collect($validated)->except('password')->all());

        if (filled($validated['password'] ?? null)) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        activity('user.updated', "Updated the account {$user->email}.", $user);

        return back()->with('status', 'User saved.');
    }

    public function show(User $user): RedirectResponse
    {
        return redirect()->route('admin.users.edit', $user);
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return back()->with('error', 'This is the only admin account. Promote someone else first.');
        }

        $email = $user->email;
        $user->delete();

        activity('user.deleted', "Deleted the account {$email}.");

        return redirect()->route('admin.users.index')->with('status', 'User deleted.');
    }

    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['status' => $user->isActive() ? 'suspended' : 'active']);

        return back()->with('status', 'Account '.($user->isActive() ? 'reactivated.' : 'suspended.'));
    }

    /**
     * Clears a user's second factor. The recovery path when someone loses the
     * phone holding their authenticator app.
     */
    public function resetTwoFactor(User $user): RedirectResponse
    {
        $this->twoFactor->disable($user);

        activity('user.two_factor_reset', "Reset two-factor authentication for {$user->email}.", $user);

        return back()->with('status', 'Two-factor authentication cleared for this user.');
    }

    private function roles(): array
    {
        return [
            User::ROLE_ADMIN => 'Administrator',
            User::ROLE_EDITOR => 'Editor',
            User::ROLE_CUSTOMER => 'Customer',
        ];
    }
}
