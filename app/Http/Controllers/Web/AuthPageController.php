<?php

namespace App\Http\Controllers\Web;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Services\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Session-based sign in / register / sign out for the student portal. The API keeps its own token login. */
class AuthPageController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route($this->homeRoute(Auth::user()->role)) : view('auth.login');
    }

    public function showRegister(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route($this->homeRoute(Auth::user()->role)) : view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'role' => ['sometimes', Rule::in([
                Role::STUDENT->value,
                Role::TP_COORDINATOR->value,
                Role::TPO->value,
                Role::SYSTEM_ADMIN->value,
            ])],
        ]);

        // Same rule as the API: unknown user, wrong password and inactive account give one message.
        $ok = Auth::attempt([
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'is_active' => true,
        ], $request->boolean('remember'));

        if (! $ok) {
            throw ValidationException::withMessages(['email' => ['These credentials do not match our records.']]);
        }

        $request->session()->regenerate();

        $user = $request->user();

        if (! $request->filled('role') && ! $user->hasRole(Role::STUDENT)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['email' => ['This portal is for students only.']]);
        }

        if ($request->filled('role') && $data['role'] !== $user->role->value) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw ValidationException::withMessages(['role' => ['The selected role does not match this account.']]);
        }

        return redirect()->intended(route($this->homeRoute($user->role)));
    }

    public function register(RegisterRequest $request, RegistrationService $registration): RedirectResponse
    {
        $user = $registration->register($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('student.dashboard')->with('status', 'Account created. Complete your profile to start applying.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function homeRoute(Role $role): string
    {
        return match ($role) {
            Role::STUDENT => 'student.dashboard',
            Role::TP_COORDINATOR => 'coordinator.dashboard',
            Role::TPO => 'tpo.dashboard',
            Role::SYSTEM_ADMIN => 'admin.dashboard',
            default => 'login',
        };
    }
}
