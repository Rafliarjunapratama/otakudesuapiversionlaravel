<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnimeController;
use App\Http\Controllers\ZerochanController;
use App\Http\Controllers\OtakotakuController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Konversi dari Express.js ke Laravel.
| Semua route di sini otomatis mendapat prefix /api oleh bootstrap/app.php
|
*/

// Redirect root ke /api/anime
Route::get('/', fn() => redirect('/anime'));

// ── Anime (OtakuDesu) ────────────────────────────────────────────────────────
Route::prefix('anime')->group(function () {
    Route::get('/',                   [AnimeController::class, 'ongoing']);      // GET /api/anime
    Route::get('/complete',           [AnimeController::class, 'complete']);     // GET /api/anime/complete
    Route::get('/complete/page/{page}', [AnimeController::class, 'completePage']); // GET /api/anime/complete/page/{page}
    Route::get('/detail',             [AnimeController::class, 'detail']);       // GET /api/anime/detail?link=...
    Route::get('/detail/video',       [AnimeController::class, 'detailVideo']); // GET /api/anime/detail/video?link=...

    // OtakOtaku
    Route::get('/otakotaku/search',   [OtakotakuController::class, 'search']);  // GET /api/anime/otakotaku/search?q=...
});

// ── Zerochan ─────────────────────────────────────────────────────────────────
Route::prefix('zerochan')->group(function () {
    Route::get('/search',     [ZerochanController::class, 'search']);     // GET /api/zerochan/search?q=...
    Route::get('/characters', [ZerochanController::class, 'characters']); // GET /api/zerochan/characters?q=...
});