<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/Login');
    })->name('login');

    Route::get('register', function () {
        return Inertia::render('Auth/Register');
    })->name('register');

    Route::get('forgot-password', function () {
        return Inertia::render('Auth/ForgotPassword');
    })->name('password.request');
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
