<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class FilamentLoginController extends Controller
{
    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string|min:6',
        ]);

        // Try to find user by username first, then email
        $user = User::where('username', $request->login)->first();

        if (!$user) {
            $user = User::where('email', $request->login)->first();
        }

        if ($user && \Illuminate\Support\Facades\Hash::check($request->password, $user->password) && $user->is_active) {
            Auth::guard('web')->login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            return redirect()->intended(filament()->getUrl());
        }

        // If authentication fails
        throw ValidationException::withMessages([
            'login' => __('filament-panels::auth.login.messages.failed'),
        ]);
    }

    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('filament.auth.login');
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(filament()->getLoginUrl());
    }
}