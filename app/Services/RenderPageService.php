<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class RenderPageService
{
    public function loadJson(string $jsonPath): array
    {
        $cacheKey = "render_page_json_{$jsonPath}";

        return Cache::remember($cacheKey, 3600, function () use ($jsonPath) {
            $fullPath = storage_path("app/data/pages/{$jsonPath}");

            if (! file_exists($fullPath)) {
                throw new \RuntimeException("JSON file not found: {$jsonPath}");
            }

            $content = file_get_contents($fullPath);
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Invalid JSON: '.json_last_error_msg());
            }

            if (! $this->validateJsonStructure($data)) {
                throw new \RuntimeException('Invalid JSON structure');
            }

            return $data;
        });
    }

    public function generateFilters(array $items): array
    {
        $filters = [];
        $excludedFields = ['id', 'name', 'image', 'description', 'price'];

        foreach ($items as $item) {
            foreach ($item as $key => $value) {
                if (in_array($key, $excludedFields)) {
                    continue;
                }

                if (! isset($filters[$key])) {
                    $filters[$key] = [];
                }

                if (is_array($value)) {
                    foreach ($value as $val) {
                        if (! in_array($val, $filters[$key])) {
                            $filters[$key][] = $val;
                        }
                    }
                } else {
                    if (! in_array($value, $filters[$key])) {
                        $filters[$key][] = $value;
                    }
                }
            }
        }

        foreach ($filters as $key => $values) {
            sort($filters[$key]);
        }

        return $filters;
    }

    public function generateCategories(array $items): array
    {
        $categories = [];
        $uncategorizedItems = [];

        foreach ($items as $item) {
            if (! isset($item['category'])) {
                $uncategorizedItems[] = $item;

                continue;
            }

            $categoryName = $item['category'];

            if (! isset($categories[$categoryName])) {
                $categories[$categoryName] = [
                    'name' => $categoryName,
                    'items' => [],
                ];
            }

            $categories[$categoryName]['items'][] = $item;
        }

        if (! empty($uncategorizedItems)) {
            $categories['All'] = [
                'name' => 'All',
                'items' => $uncategorizedItems,
            ];
        }

        return array_values($categories);
    }

    public function getItems(array $data): array
    {
        return $data['items'] ?? [];
    }

    public function findItemById(array $data, string $id): ?array
    {
        $items = $this->getItems($data);

        foreach ($items as $item) {
            if (isset($item['id']) && $item['id'] === $id) {
                return $item;
            }
        }

        return null;
    }

    public function getAdditionalFields(array $item): array
    {
        $excludedFields = ['id', 'name', 'image', 'description', 'price', 'category'];
        $additionalFields = [];

        foreach ($item as $key => $value) {
            if (! in_array($key, $excludedFields)) {
                $additionalFields[$key] = $value;
            }
        }

        return $additionalFields;
    }

    public function validateJsonStructure(array $data): bool
    {
        if (! isset($data['renders_path']) || ! is_string($data['renders_path'])) {
            return false;
        }

        if (! isset($data['items']) || ! is_array($data['items'])) {
            return false;
        }

        foreach ($data['items'] as $item) {
            if (! is_array($item)) {
                return false;
            }

            if (! isset($item['id']) || ! isset($item['name']) || ! isset($item['image'])) {
                return false;
            }
        }

        return true;
    }
}
