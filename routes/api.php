<?php
// routes/api.php

use App\Http\Controllers\Api\AnalysisController;
use App\Http\Controllers\Api\RepositoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.')->group(function () {

    // ── Repository ──────────────────────────────────────
    Route::prefix('repositories')->name('repositories.')->group(function () {

        Route::post('/analyze', [RepositoryController::class, 'analyze'])
            ->name('analyze');

        Route::get('/{owner}/{repo}', [RepositoryController::class, 'show'])
            ->name('show');

        Route::post('/metadata', [RepositoryController::class, 'metadata'])->name('repositories.metadata');
    });

    // ── Analysis ────────────────────────────────────────
    Route::prefix('analysis/{job}')->name('analysis.')->group(function () {

        Route::get('/status', [AnalysisController::class, 'status'])
            ->name('status');

        Route::get('/results', [AnalysisController::class, 'results'])
            ->name('results');

        Route::get('/file-tree', [AnalysisController::class, 'fileTree'])
            ->name('file-tree');

        Route::get('/file', [AnalysisController::class, 'fileResult'])
            ->name('file-result');

        Route::post('/file/analyze', [AnalysisController::class, 'analyzeFile'])
            ->name('file-analyze');
    });
});
