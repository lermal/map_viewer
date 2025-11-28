<?php

namespace App\Providers;

use App\Models\RenderPage;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        View::composer('header', function ($view) {
            $renderPages = RenderPage::where('is_active', true)
                ->orderBy('sort_order')
                ->get();

            $view->with('renderPages', $renderPages);
        });
    }
}
