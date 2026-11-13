<?php

namespace App\Services;

use App\Exceptions\CatalogUnavailableException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class RenderPageService
{
    /**
     * Catalog data for a page. A missing or broken file falls back to the last version that loaded,
     * so a bad publish never takes the page down.
     *
     * @throws CatalogUnavailableException when no version of the file has ever loaded
     */
    public function loadJson(string $jsonPath): array
    {
        $fullPath = $this->catalogPath($jsonPath);
        $lastGoodKey = "render_page_json_last_{$jsonPath}";

        // The modification time is part of the key, so a freshly published catalog is used immediately.
        $mtime = $this->mtime($fullPath);

        try {
            if ($mtime === null) {
                throw new RuntimeException("JSON file not found: {$jsonPath}");
            }

            return Cache::remember("render_page_json_{$jsonPath}_{$mtime}", 3600, function () use ($fullPath, $lastGoodKey) {
                $data = $this->readCatalog($fullPath);
                Cache::forever($lastGoodKey, $data);

                return $data;
            });
        } catch (RuntimeException $e) {
            Log::error("Catalog {$jsonPath} could not be loaded: {$e->getMessage()}");
        }

        return Cache::get($lastGoodKey) ?? throw new CatalogUnavailableException($jsonPath);
    }

    /**
     * Modification time of the catalog file, or null when it does not exist.
     */
    public function catalogMtime(string $jsonPath): ?int
    {
        return $this->mtime($this->catalogPath($jsonPath));
    }

    /**
     * Public URL of an item's preview image with its modification time as version,
     * so caches pick up a re-rendered image at once. Null when the file does not exist.
     */
    public function imageUrl(array $data, array $item): ?string
    {
        $relative = $this->imagePath($data, $item);
        $mtime = $this->imageMtime($data, $item);

        return $mtime === null ? null : asset('storage/'.$relative).'?v='.$mtime;
    }

    /**
     * Modification time of an item's preview image, or null when it does not exist.
     */
    public function imageMtime(array $data, array $item): ?int
    {
        $relative = $this->imagePath($data, $item);

        return $relative ? $this->mtime(storage_path('app/public/'.$relative)) : null;
    }

    private function imagePath(array $data, array $item): ?string
    {
        $image = $item['image'] ?? null;
        if (! is_string($image) || $image === '' || str_contains($image, '..')) {
            return null;
        }

        return ($data['renders_path'] ?? '').$image;
    }

    private function catalogPath(string $jsonPath): string
    {
        return storage_path("app/data/pages/{$jsonPath}");
    }

    private function mtime(string $path): ?int
    {
        clearstatcache(true, $path);

        return is_file($path) ? (filemtime($path) ?: null) : null;
    }

    private function readCatalog(string $fullPath): array
    {
        $content = @file_get_contents($fullPath);
        if ($content === false) {
            throw new RuntimeException('File could not be read');
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON: '.json_last_error_msg());
        }

        if (! is_array($data) || ! $this->validateJsonStructure($data)) {
            throw new RuntimeException('Invalid JSON structure');
        }

        return $data;
    }

    public function generateFilters(array $items): array
    {
        $filters = [];
        $excludedFields = ['id', 'name', 'image', 'description', 'price', 'map', '_jsonCategory'];

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

    private const GROUP_NAMES = [
        'Shipyard' => 'Civilian Vessels',
        'Security' => 'NSFD Fleet',
        'BlackMarket' => 'Pirate Fleet',
        'Sr' => 'Frontier Outpost Vessel',
        'Scrap' => 'Scrapyard Fleet',
        'Custom' => 'Mothership Fleet',
        'Expedition' => 'Expedition Vessels',
    ];

    /**
     * Name of a catalog group as shown in the menu, e.g. "BlackMarket" → "Pirate Fleet".
     */
    public function groupDisplayName(string $group): string
    {
        return self::GROUP_NAMES[$group] ?? $this->splitCamelCase($group);
    }

    private function splitCamelCase(string $text): string
    {
        return preg_replace('/([a-z])([A-Z])/', '$1 $2', $text);
    }

    public function generateCategories(array $items): array
    {
        $categoryOrder = [
            'Shipyard' => 1,
            'Medical' => 2,
            'Expedition' => 3,
            'Security' => 4,
            'Sr' => 5,
            'Custom' => 6,
            'Scrap' => 7,
            'BlackMarket' => 8,
            'Syndicate' => 9,
        ];

        $categories = [];

        foreach ($items as $item) {
            $groupName = null;

            if (isset($item['_jsonCategory'])) {
                $groupName = $item['_jsonCategory'];
            } elseif (isset($item['class']) && is_array($item['class']) && ! empty($item['class'])) {
                $groupName = $item['class'][0];
            } elseif (isset($item['category'])) {
                $groupName = $item['category'];
            }

            if ($groupName === null) {
                if (isset($item['name']) && ! empty($item['name'])) {
                    $firstLetter = strtoupper(substr($item['name'], 0, 1));
                    if (! ctype_alpha($firstLetter)) {
                        $firstLetter = '#';
                    }
                    $groupName = '_letter_'.$firstLetter;
                } else {
                    $groupName = '_letter_#';
                }
            }

            if (! isset($categories[$groupName])) {
                if (str_starts_with($groupName, '_letter_')) {
                    $letter = substr($groupName, 7);
                    $displayName = $letter;
                    $order = 1000 + ord($letter);
                } else {
                    $displayName = $this->groupDisplayName($groupName);
                    $order = $categoryOrder[$groupName] ?? 999;
                }
                $categories[$groupName] = [
                    'name' => $displayName,
                    'items' => [],
                    'order' => $order,
                    'sortName' => $displayName,
                ];
            }

            $categories[$groupName]['items'][] = $item;
        }

        uasort($categories, function ($a, $b) {
            $orderCompare = $a['order'] <=> $b['order'];
            if ($orderCompare !== 0) {
                return $orderCompare;
            }

            if ($a['order'] === 999 && $b['order'] === 999) {
                return strcasecmp($a['sortName'], $b['sortName']);
            }

            return 0;
        });

        $result = [];
        foreach ($categories as $category) {
            unset($category['order'], $category['sortName']);
            $result[] = $category;
        }

        return $result;
    }

    public function getItems(array $data): array
    {
        if (isset($data['items']) && is_array($data['items'])) {
            return $data['items'];
        }

        if (isset($data['categories']) && is_array($data['categories'])) {
            $items = [];
            foreach ($data['categories'] as $categoryName => $categoryItems) {
                if (is_array($categoryItems)) {
                    foreach ($categoryItems as $item) {
                        if (is_array($item)) {
                            $item['_jsonCategory'] = $categoryName;
                            $items[] = $item;
                        }
                    }
                }
            }

            return $items;
        }

        return [];
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
        $excludedFields = ['id', 'name', 'image', 'description', 'price', 'category', 'map', '_jsonCategory'];
        $additionalFields = [];

        foreach ($item as $key => $value) {
            if (! in_array($key, $excludedFields)) {
                $isEmpty = false;
                if (is_array($value)) {
                    $isEmpty = empty($value);
                } else {
                    $isEmpty = empty($value) && $value !== '0' && $value !== 0;
                }

                if (! $isEmpty) {
                    $additionalFields[$key] = $value;
                }
            }
        }

        return $additionalFields;
    }

    public function validateJsonStructure(array $data): bool
    {
        if (! isset($data['renders_path']) || ! is_string($data['renders_path'])) {
            return false;
        }

        if (isset($data['items']) && is_array($data['items'])) {
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

        if (isset($data['categories']) && is_array($data['categories'])) {
            foreach ($data['categories'] as $categoryItems) {
                if (! is_array($categoryItems)) {
                    return false;
                }

                foreach ($categoryItems as $item) {
                    if (! is_array($item)) {
                        return false;
                    }

                    if (! isset($item['id']) || ! isset($item['name']) || ! isset($item['image'])) {
                        return false;
                    }
                }
            }

            return true;
        }

        return false;
    }
}
