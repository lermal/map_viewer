<?php

use App\Services\RenderPageService;
use Illuminate\Support\Facades\Cache;

function writeCatalog(string $path, array $items, int $mtime): void
{
    file_put_contents($path, json_encode(['renders_path' => 'renders/test/', 'items' => $items]));
    touch($path, $mtime);
    clearstatcache(true, $path);
}

beforeEach(function () {
    $this->file = 'pest-catalog-'.uniqid().'.json';
    $this->path = storage_path('app/data/pages/'.$this->file);
    @mkdir(dirname($this->path), 0777, true);
});

afterEach(function () {
    @unlink($this->path);
});

it('returns a new catalog as soon as the file changes', function () {
    Cache::flush();
    $service = new RenderPageService;

    writeCatalog($this->path, [['id' => 'A', 'name' => 'Alpha', 'image' => 'a.png']], time() - 60);
    expect($service->getItems($service->loadJson($this->file)))->toHaveCount(1);

    writeCatalog($this->path, [
        ['id' => 'A', 'name' => 'Alpha', 'image' => 'a.png'],
        ['id' => 'B', 'name' => 'Beta', 'image' => 'b.png'],
    ], time());
    expect($service->getItems($service->loadJson($this->file)))->toHaveCount(2);
});

it('keeps the render data path out of filters and info fields', function () {
    $service = new RenderPageService;
    $item = ['id' => 'A', 'name' => 'Alpha', 'image' => 'a.png', 'map' => 'maps/shuttles/a.0123456789ab.json', 'class' => ['Science']];

    expect($service->generateFilters([$item]))->not->toHaveKey('map')
        ->and($service->getAdditionalFields($item))->not->toHaveKey('map')
        ->and($service->getAdditionalFields($item))->toHaveKey('class');
});
