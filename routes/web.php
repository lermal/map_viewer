<?php

use App\Http\Controllers\RenderController;
use Illuminate\Support\Facades\Route;

Route::get('/', [RenderController::class, 'index'])->name('render.index');
Route::get('/render/{slug}', [RenderController::class, 'page'])->name('render.page');
Route::get('/render/{slug}/{id}', [RenderController::class, 'show'])->name('render.show');
