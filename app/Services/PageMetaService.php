<?php

namespace App\Services;

use App\Models\RenderPage;
use Illuminate\Support\Str;

/**
 * Titles, descriptions and structured data of the catalog pages, passed to <x-page-head>.
 */
class PageMetaService
{
    private const GAME = 'Frontier Station 14';

    public function __construct(
        protected RenderPageService $renderPageService
    ) {}

    public function home(): array
    {
        $description = 'Browse renders and interactive maps of every ship and point of interest in '.self::GAME.': decks, wiring, pipes and all objects.';

        return [
            'title' => $this->siteName().' - '.self::GAME.' ship and POI renders',
            'description' => $description,
            'canonical' => route('render.index'),
            'jsonLd' => [[
                '@context' => 'https://schema.org',
                '@type' => 'WebSite',
                'name' => $this->siteName(),
                'url' => route('render.index'),
                'description' => $description,
            ]],
        ];
    }

    public function category(RenderPage $page, array $items): array
    {
        $url = route('render.page', $page->slug);
        $description = $page->meta_description ?: $page->description
            ?: 'All '.count($items).' '.$page->name.' of '.self::GAME.' with renders and interactive maps.';

        return [
            'title' => $page->meta_title
                ? $page->meta_title.' - '.$this->siteName()
                : $page->name.' of '.self::GAME.': renders and interactive maps - '.$this->siteName(),
            'description' => $this->clean($description),
            'canonical' => $url,
            'jsonLd' => [
                $this->breadcrumbs([[$page->name, $url]]),
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => $page->name,
                    'numberOfItems' => count($items),
                    'itemListElement' => array_map(fn (array $item, int $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'name' => $item['name'],
                        'url' => route('render.show', [$page->slug, $item['id']]),
                    ], array_values($items), array_keys(array_values($items))),
                ],
            ],
        ];
    }

    public function item(RenderPage $page, array $item, ?string $imageUrl): array
    {
        $url = route('render.show', [$page->slug, $item['id']]);
        $description = $this->clean((string) ($item['description'] ?? '')) ?: $this->generatedDescription($page, $item);

        $jsonLd = [$this->breadcrumbs([[$page->name, route('render.page', $page->slug)], [$item['name'], $url]])];
        if ($imageUrl) {
            $jsonLd[] = [
                '@context' => 'https://schema.org',
                '@type' => 'ImageObject',
                'name' => $item['name'].' - '.self::GAME,
                'description' => $description,
                'contentUrl' => $imageUrl,
                'url' => $url,
            ];
        }

        return [
            'title' => $item['name'].' - '.$page->name.' - '.$this->siteName(),
            'description' => $description,
            'canonical' => $url,
            'image' => $imageUrl,
            'largeImage' => $imageUrl !== null,
            'jsonLd' => $jsonLd,
        ];
    }

    /**
     * For items without their own text: name, catalog, group, size, class and price.
     */
    public function generatedDescription(RenderPage $page, array $item): string
    {
        $parts = [$item['name'].' from the '.self::GAME.' '.$page->name.' catalog.'];

        if (! empty($item['_jsonCategory'])) {
            $parts[] = 'Group: '.$this->renderPageService->groupDisplayName($item['_jsonCategory']).'.';
        }
        if (! empty($item['category']) && is_string($item['category'])) {
            $parts[] = 'Size: '.$item['category'].'.';
        }
        $classes = array_filter((array) ($item['class'] ?? []), 'is_string');
        if ($classes) {
            $parts[] = 'Class: '.implode(', ', $classes).'.';
        }
        if (! empty($item['price']) && is_numeric($item['price'])) {
            $parts[] = 'Price: '.number_format((float) $item['price'], 0, '.', ' ').' spesos.';
        }
        $parts[] = 'Interactive map with every object, wire and pipe.';

        return implode(' ', $parts);
    }

    private function breadcrumbs(array $trail): array
    {
        $trail = array_merge([[$this->siteName(), route('render.index')]], $trail);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(fn (array $step, int $i) => [
                '@type' => 'ListItem',
                'position' => $i + 1,
                'name' => $step[0],
                'item' => $step[1],
            ], $trail, array_keys($trail)),
        ];
    }

    private function clean(string $text): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($text))), 300);
    }

    private function siteName(): string
    {
        return config('app.name', 'Shipyard');
    }
}
