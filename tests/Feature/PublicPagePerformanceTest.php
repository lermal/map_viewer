<?php

use App\Models\RenderPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Cache::flush();
    $this->file = 'pest-perf-'.uniqid().'.json';
    $this->catalog = storage_path("app/data/pages/{$this->file}");
    File::ensureDirectoryExists(dirname($this->catalog));
    file_put_contents($this->catalog, json_encode(['renders_path' => 'renders/none/', 'items' => [
        ['id' => 'Alpha', 'name' => 'Alpha', 'image' => 'alpha.png', 'class' => ['Science']],
    ]]));
    $this->page = RenderPage::create(['name' => 'Ships', 'slug' => 'ships-'.uniqid(), 'json_path' => $this->file]);
});

afterEach(fn () => File::delete($this->catalog));

it('serves catalog pages without cookies and with a public cache header', function (string $url) {
    $response = $this->get(str_replace('{slug}', $this->page->slug, $url))->assertOk();

    expect($response->headers->getCookies())->toBe([])
        ->and($response->headers->get('Cache-Control'))->toContain('public')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=300');
})->with(['/', '/render/{slug}', '/render/{slug}/Alpha', '/sitemap.xml']);

it('keeps sessions where forms need them and never caches errors publicly', function () {
    expect($this->get('/user-renders')->assertOk()->headers->getCookies())->not->toBe([]);

    $missing = $this->get("/render/{$this->page->slug}/Nope")->assertNotFound();
    expect($missing->headers->get('Cache-Control'))->not->toContain('public');
});

it('does not embed unused data or the icon font', function () {
    $html = $this->get("/render/{$this->page->slug}/Alpha")->assertOk()->getContent();

    expect($html)->not->toContain('window.renderData')
        ->and($html)->not->toContain('window.filters')
        ->and($html)->not->toContain('remixicon')
        ->and($html)->toContain('<svg viewBox="0 0 24 24"')
        ->and($html)->toContain('images/stars-1.webp');
});
