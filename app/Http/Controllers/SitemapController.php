<?php

namespace App\Http\Controllers;

use App\Models\RenderPage;
use App\Services\RenderPageService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __construct(
        protected RenderPageService $renderPageService
    ) {}

    public function index(): Response
    {
        $urls = [];

        $urls[] = [
            'loc' => route('render.index'),
            'lastmod' => now()->toAtomString(),
            'priority' => '1.0',
            'changefreq' => 'daily',
        ];

        $pages = RenderPage::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        foreach ($pages as $page) {
            $urls[] = [
                'loc' => route('render.page', $page->slug),
                'lastmod' => $page->updated_at?->toAtomString() ?? now()->toAtomString(),
                'priority' => '0.8',
                'changefreq' => 'weekly',
            ];

            try {
                $data = $this->renderPageService->loadJson($page->json_path);
                $items = $this->renderPageService->getItems($data);

                foreach ($items as $item) {
                    if (isset($item['id'])) {
                        $urls[] = [
                            'loc' => route('render.show', [$page->slug, $item['id']]),
                            'lastmod' => $page->updated_at?->toAtomString() ?? now()->toAtomString(),
                            'priority' => '0.6',
                            'changefreq' => 'monthly',
                        ];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $xml = view('sitemap', ['urls' => $urls])->render();

        return response($xml, 200)
            ->header('Content-Type', 'application/xml');
    }
}
