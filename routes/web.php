<?php

use App\Http\Controllers\Api\AnalysisController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('analyzer')->name('analyzer.')->group(function () {
    Route::get('/',          [AnalysisController::class, 'index'])->name('index');
    Route::get('/insights',  [AnalysisController::class, 'insights'])->name('insights');
    Route::get('/analyze',   [AnalysisController::class, 'analyze'])->name('analyze');
});
