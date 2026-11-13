<?php

use Illuminate\Support\Facades\Route;

it('shows missing pages in the site style', function () {
    $this->get('/no-such-page')->assertNotFound()
        ->assertSee('Page Not Found')
        ->assertSee('Back to Home')
        ->assertSee('noindex', false);
});

it('never shows exception details on a server error', function () {
    config(['app.debug' => false]);
    Route::get('/pest-crash', fn () => throw new RuntimeException('secret database password'));

    $this->get('/pest-crash')->assertStatus(500)
        ->assertSee('Server Error')
        ->assertDontSee('secret database password');
});
