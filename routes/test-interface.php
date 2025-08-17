<?php

declare(strict_types=1);

use App\Http\Controllers\ExtractorDemoController;
use Illuminate\Support\Facades\Route;

Route::get('/extractor-demo', [ExtractorDemoController::class, 'demo']);
Route::post('/extractor-upload', [ExtractorDemoController::class, 'upload']);