<?php

use App\Http\Controllers\RenderController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RenderController::class, 'index'])->name('render.index');
Route::get('/render/{slug}', [RenderController::class, 'page'])->name('render.page');
Route::get('/render/{slug}/{id}', [RenderController::class, 'show'])->name('render.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
