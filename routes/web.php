<?php

use App\Http\Controllers\Auth\UsernameLoginController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Default welcome route - redirect to Filament admin panel
Route::get('/', function () {
    return redirect('/admin');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Show login form
    Route::get('/login', [UsernameLoginController::class, 'showLoginForm'])->name('login');
    
    // Handle login request
    Route::post('/login', [UsernameLoginController::class, 'login']);
    
    // Logout route
    Route::post('/logout', [UsernameLoginController::class, 'logout'])->name('logout');
});

// Dashboard route (protected)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});

// API route for checking login
Route::post('/check-login', [UsernameLoginController::class, 'checkLogin'])->name('check.login');
