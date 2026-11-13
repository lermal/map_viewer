<?php

namespace App\Http\Controllers;

use App\Models\RenderPage;
use App\Services\RenderPageService;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function __construct(
        protected RenderPageService $renderPageService
    ) {}

    /**
     * Dates come from the published files: an item page changes with its image,
     * a list page with its catalog or its settings, the home page with the newest of them.
     */
    public function index(): Response
    {
        $urls = [];
        $newest = null;

        $pages = RenderPage::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($pages as $page) {
            $pageModified = max($this->renderPageService->catalogMtime($page->json_path) ?? 0, $page->updated_at?->timestamp ?? 0);
            $newest = max($newest ?? 0, $pageModified);

            $urls[] = [
                'loc' => route('render.page', $page->slug),
                'lastmod' => $this->date($pageModified),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];

            try {
                $data = $this->renderPageService->loadJson($page->json_path);
            } catch (\Exception) {
                continue;
            }

            foreach ($this->renderPageService->getItems($data) as $item) {
                if (! isset($item['id'])) {
                    continue;
                }

                $urls[] = [
                    'loc' => route('render.show', [$page->slug, $item['id']]),
                    'lastmod' => $this->date($this->renderPageService->imageMtime($data, $item) ?? $pageModified),
                    'priority' => '0.6',
                    'changefreq' => 'monthly',
                ];
            }
        }

        array_unshift($urls, [
            'loc' => route('render.index'),
            'lastmod' => $this->date($newest),
            'priority' => '1.0',
            'changefreq' => 'daily',
        ]);

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response('<?xml version="1.0" encoding="UTF-8"?>'."\n".$xml, 200)
            ->header('Content-Type', 'application/xml');
    }

    private function date(?int $timestamp): ?string
    {
        return $timestamp ? Carbon::createFromTimestamp($timestamp)->toAtomString() : null;
    }
}
