<?php

declare(strict_types=1);

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Authentication Pages (SPA)
Route::get('login', function () {
    return Inertia::render('Auth/Login');
})->name('login');

Route::get('register', function () {
    return Inertia::render('Auth/Register');
})->name('register');

Route::get('forgot-password', function () {
    return Inertia::render('Auth/ForgotPassword');
})->name('password.request');

Route::get('reset-password/{token}', function (string $token) {
    return Inertia::render('Auth/ResetPassword', ['token' => $token]);
})->name('password.reset');

// Public Home Page
Route::get('/', [DashboardController::class, 'index'])->name('home');

// Dashboard (with real user data)
Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

// Documents (placeholder for future implementation)
Route::get('documents', function () {
    return Inertia::render('Documents/Index');
})->name('documents.index');

// Include extractor test routes
require __DIR__ . '/test-extractor.php';
require __DIR__ . '/test-interface.php';
