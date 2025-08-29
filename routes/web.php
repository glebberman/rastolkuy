<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public Authentication Pages (SPA)
Route::get('login', fn () => Inertia::render('Auth/Login'))->name('login');
Route::get('register', fn () => Inertia::render('Auth/Register'))->name('register');
Route::get('forgot-password', fn () => Inertia::render('Auth/ForgotPassword'))->name('password.request');

// Public Home Page
Route::get('/', fn () => Inertia::render('Dashboard'))->name('home');

// Dashboard (SPA - authentication handled in frontend)
Route::get('dashboard', fn () => Inertia::render('Dashboard'))->name('dashboard');

// Documents (placeholder for future implementation)
Route::get('documents', fn () => Inertia::render('Documents/Index'))->name('documents.index');

// Include extractor test routes
require __DIR__ . '/test-extractor.php';
require __DIR__ . '/test-interface.php';
