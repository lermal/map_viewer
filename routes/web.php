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

Route::get('/images/renders/{image}', function (string $image) {
    $imageLower = strtolower($image);
    $imageName = pathinfo($imageLower, PATHINFO_FILENAME);

    $possiblePaths = [
        "renders/shuttles/{$imageName}.png",
        "renders/shuttles/{$imageName}.webp",
        "renders/shuttles/{$imageLower}",
    ];

    foreach ($possiblePaths as $imagePath) {
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->response($imagePath);
        }
    }

    abort(404);
})->where('image', '.*')->name('images.renders.shuttles');

Route::get('/images/renders/{shuttle}/{image}', function (string $shuttle, string $image) {
    $shuttleLower = strtolower($shuttle);

    $possiblePaths = [
        "renders/shuttles/{$shuttleLower}.png",
        "renders/shuttles/{$shuttleLower}.webp",
        "renders/shuttles/{$image}",
    ];

    foreach ($possiblePaths as $imagePath) {
        if (Storage::disk('public')->exists($imagePath)) {
            return Storage::disk('public')->response($imagePath);
        }
    }

    abort(404);
})->where('image', '.*')->name('images.renders');
