<?php

use App\Exceptions\CatalogUnavailableException;
use App\Models\RenderPage;
use App\Services\RenderPageService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    Cache::flush();
    $this->id = uniqid();
    $this->file = "pest-catalog-{$this->id}.json";
    $this->catalog = storage_path("app/data/pages/{$this->file}");
    $this->renders = "renders/pest-{$this->id}/";
    File::ensureDirectoryExists(dirname($this->catalog));
    File::ensureDirectoryExists(storage_path('app/public/'.$this->renders));
    $this->page = RenderPage::create(['name' => 'Ships', 'slug' => "ships-{$this->id}", 'json_path' => $this->file]);
});

afterEach(function () {
    File::delete($this->catalog);
    File::deleteDirectory(storage_path('app/public/'.$this->renders));
});

function catalogWith(array $items, int $mtime): void
{
    $path = test()->catalog;
    file_put_contents($path, json_encode(['renders_path' => test()->renders, 'items' => $items]));
    touch($path, $mtime);
    clearstatcache(true, $path);
}

function renderImage(string $name, int $mtime): void
{
    $path = storage_path('app/public/'.test()->renders.$name);
    file_put_contents($path, 'png');
    touch($path, $mtime);
    clearstatcache(true, $path);
}

const ALPHA = ['id' => 'Alpha', 'name' => 'Alpha', 'image' => 'alpha.png'];
const BETA = ['id' => 'Beta', 'name' => 'Beta', 'image' => 'beta.png'];

it('keeps serving the last good catalog while the file is broken', function () {
    catalogWith([ALPHA, BETA], time() - 60);
    $service = new RenderPageService;
    expect($service->getItems($service->loadJson($this->file)))->toHaveCount(2);

    Log::spy();
    file_put_contents($this->catalog, '{"renders_path": "renders/', LOCK_EX);
    touch($this->catalog, time());

    expect($service->getItems($service->loadJson($this->file)))->toHaveCount(2);
    Log::shouldHaveReceived('error')->once();

    $this->get(route('render.page', $this->page->slug))->assertOk()->assertSee('Beta');
});

it('answers 503 instead of 500 when a catalog has never loaded', function () {
    expect(fn () => (new RenderPageService)->loadJson($this->file))->toThrow(CatalogUnavailableException::class);

    $this->get(route('render.page', $this->page->slug))->assertStatus(503)->assertHeader('Retry-After', '300');
    $this->get(route('render.show', [$this->page->slug, 'Alpha']))->assertStatus(503);
});

it('versions image URLs by file date', function () {
    $service = new RenderPageService;
    $data = ['renders_path' => $this->renders];
    $then = time() - 3600;

    renderImage('alpha.png', $then);
    $first = $service->imageUrl($data, ALPHA);
    expect($first)->toEndWith("/storage/{$this->renders}alpha.png?v={$then}")
        ->and($service->imageUrl($data, ALPHA))->toBe($first);

    renderImage('alpha.png', $then + 10);
    expect($service->imageUrl($data, ALPHA))->not->toBe($first)
        ->and($service->imageUrl($data, BETA))->toBeNull()
        ->and($service->imageUrl($data, ['image' => '../../.env']))->toBeNull();
});

it('shows a placeholder and no og:image when an item has neither map nor image', function () {
    renderImage('alpha.png', time() - 60);
    catalogWith([ALPHA, BETA], time() - 60);

    $this->get(route('render.show', [$this->page->slug, 'Alpha']))->assertOk()
        ->assertSee('property="og:image"', false)
        ->assertSee('alpha.png?v=', false)
        ->assertDontSee('Render is being prepared');

    $this->get(route('render.show', [$this->page->slug, 'Beta']))->assertOk()
        ->assertSee('<meta property="og:image" content="'.asset('images/Logo.png').'">', false)
        ->assertDontSee('class="render-viewer__image"', false)
        ->assertDontSee('data-fallback-src', false)
        ->assertSee('Render is being prepared');
});

it('dates sitemap entries by the published files', function () {
    $image = time() - 7200;
    $catalog = time() - 3600;
    renderImage('alpha.png', $image);
    catalogWith([ALPHA, BETA], $catalog);
    $this->page->forceFill(['updated_at' => now()->subDays(3)])->saveQuietly();

    $first = $this->get('/sitemap.xml')->assertOk()->getContent();
    $this->travel(5)->minutes();
    $second = $this->get('/sitemap.xml')->getContent();

    $date = fn (int $t) => Carbon::createFromTimestamp($t)->toAtomString();
    $lastmod = function (string $xml, string $loc) {
        preg_match('#<loc>'.preg_quote($loc, '#').'</loc>\s*<lastmod>([^<]+)</lastmod>#', $xml, $m);

        return $m[1] ?? null;
    };

    expect($second)->toBe($first)
        ->and($lastmod($first, route('render.show', [$this->page->slug, 'Alpha'])))->toBe($date($image))
        ->and($lastmod($first, route('render.show', [$this->page->slug, 'Beta'])))->toBe($date($catalog))
        ->and($lastmod($first, route('render.page', $this->page->slug)))->toBe($date($catalog))
        ->and($lastmod($first, route('render.index')))->toBe($date($catalog));
});
