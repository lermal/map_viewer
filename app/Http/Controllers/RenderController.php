<?php

namespace App\Http\Controllers;

use App\Models\RenderPage;
use App\Services\PageMetaService;
use App\Services\RenderPageService;
use App\Services\VisitTrackerService;
use Illuminate\Http\Request;

class RenderController extends Controller
{
    public function __construct(
        protected RenderPageService $renderPageService,
        protected VisitTrackerService $visitTracker,
        protected PageMetaService $pageMeta
    ) {}

    public function index(Request $request)
    {
        $this->visitTracker->track($request);

        $pages = RenderPage::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $meta = $this->pageMeta->home();

        return view('render.index', compact('pages', 'meta'));
    }

    public function page(Request $request, string $slug)
    {
        $page = RenderPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $this->visitTracker->track($request, $page);

        $data = $this->renderPageService->loadJson($page->json_path);
        $items = $this->renderPageService->getItems($data);
        $filters = $this->renderPageService->generateFilters($items);
        $categories = $this->renderPageService->generateCategories($items);

        $meta = $this->pageMeta->category($page, $items);

        return view('render.page', compact('page', 'items', 'filters', 'categories', 'data', 'meta'));
    }

    public function show(Request $request, string $slug, string $id)
    {
        $page = RenderPage::where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $data = $this->renderPageService->loadJson($page->json_path);
        $item = $this->renderPageService->findItemById($data, $id);

        if (! $item) {
            abort(404);
        }

        $this->visitTracker->track($request, $page);

        $items = $this->renderPageService->getItems($data);
        $filters = $this->renderPageService->generateFilters($items);
        $categories = $this->renderPageService->generateCategories($items);
        $additionalFields = $this->renderPageService->getAdditionalFields($item);
        $imageUrl = $this->renderPageService->imageUrl($data, $item);
        $meta = $this->pageMeta->item($page, $item, $imageUrl);

        return view('render.show', compact('page', 'item', 'additionalFields', 'data', 'items', 'filters', 'categories', 'imageUrl', 'meta'));
    }
}
