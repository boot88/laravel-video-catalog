<?php

use App\Http\Controllers\CatalogController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VideoController;
use Illuminate\Support\Facades\Route;

Route::get('/', [VideoController::class, 'index'])->name('video.index');
Route::get('/videos', [VideoController::class, 'browse'])->name('videos.browse');
Route::get('/search', [VideoController::class, 'browse'])->name('videos.search');

Route::get('/category/{category:slug}', [VideoController::class, 'category'])->name('categories.show');
Route::get('/models', [CatalogController::class, 'models'])->name('models.index');
Route::get('/models/{model:slug}', [CatalogController::class, 'model'])->name('models.show');
Route::get('/brands', [CatalogController::class, 'brands'])->name('brands.index');
Route::get('/brands/{brand:slug}', [CatalogController::class, 'brand'])->name('brands.show');
Route::get('/collections', [CatalogController::class, 'collections'])->name('collections.index');
Route::get('/collections/{collection:slug}', [CatalogController::class, 'collection'])->name('collections.show');
Route::get('/tags/{tag:slug}', [CatalogController::class, 'tag'])->name('tags.show');

Route::get('/favorites', [VideoController::class, 'favorites'])->name('favorites.index');
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/credits', [PageController::class, 'credits'])->name('credits');
Route::get('/privacy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/video/{slug}', [VideoController::class, 'show'])->name('video.show');
Route::get('/video/{slug}/fullscreen', [VideoController::class, 'fullscreen'])->name('video.fullscreen');
Route::post('/video/{slug}/comment', [VideoController::class, 'storeComment'])->name('video.comment.store');
Route::post('/video/{slug}/like', [VideoController::class, 'like'])->name('video.like');
Route::post('/video/{slug}/favorite', [VideoController::class, 'favorite'])->name('video.favorite');

Route::get('/thumbnail/placeholder/{seed}.svg', [VideoController::class, 'thumbnailPlaceholder'])
    ->where('seed', '[A-Za-z0-9_-]{1,64}')
    ->name('video.thumbnail.placeholder');
