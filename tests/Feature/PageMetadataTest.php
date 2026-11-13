<?php

use App\Models\RenderPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    Cache::flush();
    $this->id = uniqid();
    $this->file = "pest-seo-{$this->id}.json";
    $this->catalog = storage_path("app/data/pages/{$this->file}");
    $this->renders = "renders/pest-seo-{$this->id}/";
    File::ensureDirectoryExists(dirname($this->catalog));
    File::ensureDirectoryExists(storage_path('app/public/'.$this->renders));
    file_put_contents(storage_path('app/public/'.$this->renders.'barnacle.png'), 'png');
    file_put_contents($this->catalog, json_encode(['renders_path' => $this->renders, 'categories' => [
        'Shipyard' => [
            ['id' => 'Barnacle', 'name' => 'Barnacle', 'image' => 'barnacle.png', 'price' => 20000, 'category' => 'Small', 'class' => ['Salvage'], 'description' => 'A small salvage ship.'],
        ],
        'RestStop' => [
            ['id' => 'Cove', 'name' => 'Pirate Cove', 'image' => 'cove.png', 'price' => 0, 'category' => 'Small', 'class' => [], 'description' => ''],
        ],
    ]]));
    $this->page = RenderPage::create(['name' => 'Shuttles', 'slug' => "shuttles-{$this->id}", 'json_path' => $this->file]);
});

afterEach(function () {
    File::delete($this->catalog);
    File::deleteDirectory(storage_path('app/public/'.$this->renders));
});

function jsonLd(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

    return collect($m[1])->map(fn ($json) => json_decode($json, true))->keyBy('@type')->all();
}

function meta(string $html, string $attribute, string $name): ?string
{
    preg_match('#<meta '.$attribute.'="'.preg_quote($name, '#').'" content="([^"]*)"#', $html, $m);

    return isset($m[1]) ? html_entity_decode($m[1], ENT_QUOTES) : null;
}

it('gives every catalog page complete metadata, icons and one h1', function (string $url) {
    $html = $this->get(str_replace('{slug}', $this->page->slug, $url))->assertOk()->getContent();

    expect(meta($html, 'name', 'description'))->not->toBeEmpty();
    foreach (['og:title', 'og:description', 'og:url', 'og:image', 'og:site_name'] as $property) {
        expect(meta($html, 'property', $property))->not->toBeEmpty();
    }
    expect(meta($html, 'name', 'twitter:card'))->not->toBeEmpty()
        ->and($html)->toContain('rel="canonical"')
        ->and($html)->toContain('rel="icon"')
        ->and($html)->toContain('rel="apple-touch-icon"')
        ->and($html)->toContain('rel="manifest"')
        ->and(substr_count($html, '<h1'))->toBe(1);
})->with(['/', '/render/{slug}', '/render/{slug}/Barnacle', '/render/{slug}/Cove']);

it('uses the address without query parameters as canonical', function () {
    $html = $this->get("/render/{$this->page->slug}?utm_source=x")->getContent();

    expect($html)->toContain('<link rel="canonical" href="'.route('render.page', $this->page->slug).'">');
});

it('describes the home page as a website', function () {
    $ld = jsonLd($this->get('/')->getContent());

    expect($ld['WebSite']['url'])->toBe(route('render.index'));
});

it('links every list item to its page and describes the list', function () {
    $html = $this->get("/render/{$this->page->slug}")->getContent();
    $ld = jsonLd($html);

    expect($html)->toContain('href="'.route('render.show', [$this->page->slug, 'Barnacle']).'"')
        ->and($html)->toContain('href="'.route('render.show', [$this->page->slug, 'Cove']).'"')
        ->and($ld['ItemList']['numberOfItems'])->toBe(2)
        ->and($ld['ItemList']['itemListElement'][1]['url'])->toBe(route('render.show', [$this->page->slug, 'Cove']))
        ->and(array_column($ld['BreadcrumbList']['itemListElement'], 'name'))->toBe(['Shipyard', 'Shuttles']);
});

it('marks up an item page with breadcrumbs, its image and its name as h1', function () {
    $html = $this->get("/render/{$this->page->slug}/Barnacle")->getContent();
    $ld = jsonLd($html);

    expect(array_column($ld['BreadcrumbList']['itemListElement'], 'name'))->toBe(['Shipyard', 'Shuttles', 'Barnacle'])
        ->and($ld['ImageObject']['contentUrl'])->toContain("{$this->renders}barnacle.png?v=")
        ->and($html)->toMatch('#<h1 class="render-info-panel__title">Barnacle</h1>#')
        ->and(meta($html, 'name', 'description'))->toBe('A small salvage ship.');
});

it('writes a description for items that have none', function () {
    $html = $this->get("/render/{$this->page->slug}/Cove")->getContent();
    $description = meta($html, 'name', 'description');

    expect($description)->toContain('Pirate Cove')
        ->and($description)->toContain('Frontier Station 14')
        ->and($description)->toContain('Rest Stop')
        ->and(jsonLd($html))->not->toHaveKey('ImageObject');
});

it('points crawlers to the sitemap and away from private pages', function () {
    $robots = file_get_contents(public_path('robots.txt'));

    expect($robots)->toContain('Sitemap: https://shipyard.frontierstation14.com/sitemap.xml')
        ->and($robots)->toContain('Disallow: /admin')
        ->and($robots)->toContain('Disallow: /api/')
        ->and($robots)->toContain('Disallow: /user-renders/create');

    $manifest = json_decode(file_get_contents(public_path('site.webmanifest')), true);
    expect($manifest['name'])->not->toBeEmpty()
        ->and($manifest['short_name'])->not->toBeEmpty();
});
