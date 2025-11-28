<?php

use App\Http\Controllers\Api\ShuttleApiController;
use App\Http\Controllers\RenderController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\UserRenderController;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', [RenderController::class, 'index'])->name('render.index');
Route::get('/render/{slug}', [RenderController::class, 'page'])->name('render.page');
Route::get('/render/{slug}/{id}', [RenderController::class, 'show'])->name('render.show');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/user-renders', [UserRenderController::class, 'index'])->name('user-renders.index');
Route::get('/user-renders/create', [UserRenderController::class, 'create'])->name('user-renders.create');
Route::post('/user-renders', [UserRenderController::class, 'store'])->name('user-renders.store');
Route::get('/user-renders/{slug}', [UserRenderController::class, 'show'])->name('user-renders.show')->where('slug', '[a-zA-Z0-9]{32}');

Route::get('/api/shuttles/get', [ShuttleApiController::class, 'getShuttles'])->name('api.shuttles.get');

Route::get('/images/renders/{shuttle}/{image}', function (string $shuttle, string $image) {
    $shuttleLower = strtolower($shuttle);
    $imageName = pathinfo($image, PATHINFO_FILENAME);
    
    if (str_starts_with($imageName, $shuttleLower . '-')) {
        $possiblePaths = [
            "renders/shuttles/{$shuttleLower}.png",
            "renders/shuttles/{$shuttleLower}.webp",
        ];

        foreach ($possiblePaths as $imagePath) {
            if (Storage::disk('public')->exists($imagePath)) {
                return Storage::disk('public')->response($imagePath);
            }
        }
    }

    abort(404);
})->where('image', '.*')->name('images.renders');
