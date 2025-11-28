<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RenderPage;
use App\Services\RenderPageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShuttleApiController extends Controller
{
    public function __construct(
        protected RenderPageService $renderPageService
    ) {}

    public function getShuttles(Request $request): JsonResponse
    {
        $page = RenderPage::where('slug', 'shuttles')
            ->where('is_active', true)
            ->first();

        if (! $page) {
            return response()->json([], 200);
        }

        $data = $this->renderPageService->loadJson($page->json_path);
        $items = $this->renderPageService->getItems($data);

        $result = [];

        foreach ($items as $item) {
            $group = $item['group'] ?? 'Shipyard';
            $defaultVariant = 'default';

            if (! isset($result[$group])) {
                $result[$group] = [];
            }

            if (! isset($result[$group][$defaultVariant])) {
                $result[$group][$defaultVariant] = [];
            }

            $shuttleData = [
                'id' => $item['id'],
                'name' => $item['name'],
                'description' => $item['description'] ?? '',
                'price' => $item['price'] ?? 0,
                'category' => $item['category'] ?? 'Small',
                'group' => $group,
                'shuttlePath' => $item['shuttlePath'] ?? null,
                'guidebookPage' => $item['guidebookPage'] ?? null,
                'class' => $item['class'] ?? [],
                'engine' => $item['engines'] ?? ($item['engine'] ?? []),
            ];

            if (isset($item['access'])) {
                $shuttleData['access'] = $item['access'];
            }

            if (isset($item['mapchecker_group_override'])) {
                $shuttleData['mapchecker_group_override'] = $item['mapchecker_group_override'];
            }

            $result[$group][$defaultVariant][] = $shuttleData;
        }

        return response()->json($result);
    }
}
