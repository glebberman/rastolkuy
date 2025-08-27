<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');
    
    Route::post('login', function () {
        // Login logic will be implemented later
        return redirect()->route('dashboard');
    });

    Route::get('register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');
    
    Route::post('register', function () {
        // Register logic will be implemented later
        return redirect()->route('dashboard');
    });

    Route::get('forgot-password', function () {
        return Inertia::render('Auth/ForgotPassword');
    })->name('password.request');
    
    Route::post('forgot-password', function () {
        // Forgot password logic will be implemented later
        return back()->with('status', 'Password reset link sent!');
    })->name('password.email');
});

// Dashboard Routes
Route::middleware('auth')->group(function () {
    Route::get('/', function () {
        return Inertia::render('Dashboard', [
            'recentDocuments' => [],
            'stats' => [
                'total_documents' => 0,
                'processed_today' => 0,
                'success_rate' => 100,
                'total_savings' => 0,
            ]
        ]);
    })->name('dashboard');

    Route::get('dashboard', function () {
        return Inertia::render('Dashboard', [
            'recentDocuments' => [],
            'stats' => [
                'total_documents' => 0,
                'processed_today' => 0,
                'success_rate' => 100,
                'total_savings' => 0,
            ]
        ]);
    })->name('dashboard.index');
});

// Include extractor test routes
require __DIR__ . '/test-extractor.php';
require __DIR__ . '/test-interface.php';
