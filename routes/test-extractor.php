<?php

declare(strict_types=1);

use App\Http\Controllers\ExtractorDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/test-extractor', [ExtractorDemoController::class, 'testBasic']);
Route::get('/test-extractor-streaming', [ExtractorDemoController::class, 'testStreaming']);
