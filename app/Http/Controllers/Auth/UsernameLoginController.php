<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

class UsernameLoginController extends Controller
{
    /**
     * Show the login form.
     */
    public function showLoginForm()
    {
        return view('auth.username.login');
    }

    /**
     * Handle a login request to the application.
     */
    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
        ]);

        $loginField = $request->login;

        // Determine if login is email or username
        $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        // Attempt to authenticate using default web guard
        $credentials = [
            $fieldType => $loginField,
            'password' => $request->password,
        ];

        // Use the web guard (default)
        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'))->with('success', __('تم تسجيل الدخول بنجاح'));
        }

        // If authentication fails
        throw ValidationException::withMessages([
            'login' => __('بيانات الدخول غير صحيحة'),
        ]);
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', __('تم تسجيل الخروج بنجاح'));
    }

    /**
     * Check if user can authenticate with username or email
     */
    public function checkLogin(Request $request)
    {
        $request->validate([
            'login' => 'required|string',
        ]);

        $loginField = $request->login;
        $fieldType = filter_var($loginField, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($fieldType, $loginField)->first();

        return response()->json([
            'exists' => (bool) $user,
            'field' => $fieldType,
            'value' => $user ? $user->$fieldType : null,
        ]);
    }

    /**
     * Get the failed login response instance.
     */
    protected function sendFailedLoginResponse(Request $request)
    {
        throw ValidationException::withMessages([
            'username' => [__('بيانات الدخول غير صحيحة')],
        ]);
    }
}