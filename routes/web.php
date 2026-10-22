<?php

use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoController::class, 'index'])->name('video.index');
Route::get('/video/{slug}', [VideoController::class, 'show'])->name('video.show');
Route::get('/video/{slug}/fullscreen', [VideoController::class, 'fullscreen'])->name('video.fullscreen');
Route::get('/thumbnail/placeholder/{seed}.svg', [VideoController::class, 'thumbnailPlaceholder'])
    ->where('seed', '[A-Za-z0-9_-]{1,64}')
    ->name('video.thumbnail.placeholder');
Route::post('/video/{slug}/comment', [VideoController::class, 'storeComment'])->name('video.comment.store');
Route::post('/video/{slug}/like', [VideoController::class, 'like'])->name('video.like');
