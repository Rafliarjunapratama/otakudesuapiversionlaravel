<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnimeController;
use App\Http\Controllers\ZerochanController;

// Root redirect
Route::get('/', function () {
    return redirect('/api/anime');
});

// Anime Routes
Route::prefix('api/anime')->group(function () {
    Route::get('/', [AnimeController::class, 'ongoingAnime']);
    Route::get('/complete', [AnimeController::class, 'completeAnime']);
    Route::get('/complete/page/{page}', [AnimeController::class, 'completeAnimePage']);
    Route::get('/detail', [AnimeController::class, 'animeDetail']);
    Route::get('/detail/video', [AnimeController::class, 'animeDetailVideo']);
    Route::get('/otakotaku/search', [AnimeController::class, 'otakotakuSearch']);
});

// Zerochan Routes
Route::prefix('api/zerochan')->group(function () {
    Route::get('/search', [ZerochanController::class, 'search']);
    Route::get('/characters', [ZerochanController::class, 'characters']);
});