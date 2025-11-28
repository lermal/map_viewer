<?php

use App\Http\Controllers\Api\ShuttleApiController;
use App\Http\Controllers\RenderController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [RenderController::class, 'index'])->name('render.index');
Route::get('/render/{slug}', [RenderController::class, 'page'])->name('render.page');
Route::get('/render/{slug}/{id}', [RenderController::class, 'show'])->name('render.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/api/shuttles/get', [ShuttleApiController::class, 'getShuttles'])->name('api.shuttles.get');
