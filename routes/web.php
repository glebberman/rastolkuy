<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Authentication Routes
Route::middleware('guest')->group(function () {
    Route::get('login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'create'])
        ->name('login');
    
    Route::post('login', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'store']);

    Route::get('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'create'])
        ->name('register');
    
    Route::post('register', [\App\Http\Controllers\Auth\RegisteredUserController::class, 'store']);

    Route::get('forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'create'])
        ->name('password.request');
    
    Route::post('forgot-password', [\App\Http\Controllers\Auth\PasswordResetLinkController::class, 'store'])
        ->name('password.email');
});

// Logout route
Route::post('logout', [\App\Http\Controllers\Auth\AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

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
