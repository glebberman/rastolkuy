<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['message' => 'Laravel is working!', 'status' => 'success']);
});

// Include extractor test routes
require __DIR__ . '/test-extractor.php';
require __DIR__ . '/test-interface.php';
