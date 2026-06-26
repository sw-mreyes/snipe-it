<?php

namespace App\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Cross-entity global search (custom fork feature).
 *
 * Shared by the web and API search controllers so the query logic lives in one
 * place. Searches assets, accessories, components and consumables via each
 * model's existing TextSearch scope, only for the entity types the current
 * user is allowed to view.
 */
class GlobalSearchService
{
    /**
     * Per-type cap so one entity type cannot dominate the combined result.
     */
    public const PER_TYPE_LIMIT = 50;

    /**
     * @return Collection collection of ['type' => string, 'model' => Model]
     */
    public function search(string $term): Collection
    {
        $results = collect();

        if (trim($term) === '') {
            return $results;
        }

        foreach ($this->searchableTypes() as $type => $class) {
            if (! Gate::allows('index', $class)) {
                continue;
            }

            $matches = $class::with($this->eagerLoadsFor($type))
                ->TextSearch($term)
                ->take(self::PER_TYPE_LIMIT)
                ->get()
                ->unique('id');

            foreach ($matches as $match) {
                $results->push(['type' => $type, 'model' => $match]);
            }
        }

        return $results;
    }

    /**
     * @return array<string, class-string>
     */
    public function searchableTypes(): array
    {
        return [
            'asset' => Asset::class,
            'accessory' => Accessory::class,
            'component' => Component::class,
            'consumable' => Consumable::class,
            'category' => Category::class,
            'assetModel' => AssetModel::class,
        ];
    }

    private function eagerLoadsFor(string $type): array
    {
        return match ($type) {
            'asset' => ['model', 'location', 'assignedTo'],
            // Categories and asset-models have neither a category nor a location relation.
            'category', 'assetModel' => [],
            default => ['category', 'location'],
        };
    }
}
