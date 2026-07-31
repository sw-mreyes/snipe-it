<?php

namespace App\Services\GlobalSearch;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Cross-entity global search (custom fork feature).
 *
 * Shared by the web and API controllers. Three things make this more than a
 * name filter, all of them deliberate (see SPEC.md §3.1a):
 *
 *  - a term may be a printed tag (`AC-12`, `SW-000134`), which resolves
 *    directly to that item;
 *  - a term matching a container — a location, category or asset model — yields
 *    both the container itself and the items inside it;
 *  - a comma-separated query runs as several terms whose results are unioned.
 *
 * Only entity types the current user may index are ever queried, so the result
 * set degrades gracefully instead of erroring for restricted users.
 */
class GlobalSearchService
{
    /**
     * Per-type cap, so one entity type cannot dominate the combined result.
     */
    public const PER_TYPE_LIMIT = 50;

    /**
     * Entity types that can appear as a result row.
     *
     * @var array<string, class-string>
     */
    public const TYPES = [
        'asset' => Asset::class,
        'accessory' => Accessory::class,
        'component' => Component::class,
        'consumable' => Consumable::class,
        'location' => Location::class,
        'category' => Category::class,
        'assetModel' => AssetModel::class,
    ];

    /**
     * Types searched by free text via each model's TextSearch scope.
     */
    private const TEXT_SEARCHABLE = ['asset', 'accessory', 'component', 'consumable'];

    /**
     * Container types: matched on name, and expanded to their contents.
     */
    private const CONTAINERS = ['location', 'category', 'assetModel'];

    /**
     * @return Collection<int, array{type: string, model: object}>
     */
    public function search(string $query): Collection
    {
        $results = collect();

        foreach ($this->terms($query) as $term) {
            foreach ($this->searchTerm($term) as $hit) {
                $results->push($hit);
            }
        }

        return $this->capAndDeduplicate($results);
    }

    /**
     * Split a query into its individual terms. Commas separate terms so several
     * scanned tags can be pasted at once.
     *
     * @return array<int, string>
     */
    public function terms(string $query): array
    {
        return array_values(array_filter(array_map('trim', explode(',', $query)), fn ($t) => $t !== ''));
    }

    /**
     * @return array<int, array{type: string, model: object}>
     */
    private function searchTerm(string $term): array
    {
        // A printed tag resolves straight to its item, and short-circuits the
        // rest: scanning a label should not also return everything named like it.
        if ($tagged = $this->resolveTag($term)) {
            return $tagged;
        }

        $hits = [];

        foreach (self::TEXT_SEARCHABLE as $type) {
            foreach ($this->textSearch($type, $term) as $model) {
                $hits[] = ['type' => $type, 'model' => $model];
            }
        }

        foreach (self::CONTAINERS as $type) {
            foreach ($this->nameSearch($type, $term) as $container) {
                $hits[] = ['type' => $type, 'model' => $container];

                foreach ($this->contentsOf($type, $container) as $hit) {
                    $hits[] = $hit;
                }
            }
        }

        return $hits;
    }

    /**
     * @return array<int, array{type: string, model: object}>
     */
    private function resolveTag(string $term): array
    {
        $hits = [];

        foreach (ItemTag::resolve($term) as $model) {
            $type = $this->typeOf($model);

            if (! $type || ! $this->allowed($type)) {
                continue;
            }

            $hits[] = ['type' => $type, 'model' => $model];

            // A location tag (BX-…) also lists what is stored there.
            foreach ($this->contentsOf($type, $model) as $hit) {
                $hits[] = $hit;
            }
        }

        return $hits;
    }

    /**
     * The items held by a container. Non-containers have none.
     *
     * @return array<int, array{type: string, model: object}>
     */
    private function contentsOf(string $type, object $container): array
    {
        $hits = [];

        $scopes = match ($type) {
            'location' => [
                'asset' => fn ($q) => $q->where('assets.location_id', $container->id),
                'accessory' => fn ($q) => $q->where('location_id', $container->id),
                'component' => fn ($q) => $q->where('location_id', $container->id),
                'consumable' => fn ($q) => $q->where('location_id', $container->id),
            ],
            'category' => [
                'asset' => fn ($q) => $q->whereHas('model', fn ($m) => $m->where('category_id', $container->id)),
                'accessory' => fn ($q) => $q->where('category_id', $container->id),
                'component' => fn ($q) => $q->where('category_id', $container->id),
                'consumable' => fn ($q) => $q->where('category_id', $container->id),
            ],
            'assetModel' => [
                'asset' => fn ($q) => $q->where('assets.model_id', $container->id),
            ],
            default => [],
        };

        foreach ($scopes as $itemType => $scope) {
            if (! $this->allowed($itemType)) {
                continue;
            }

            $class = self::TYPES[$itemType];

            $models = $class::query()
                ->select((new $class)->getTable().'.*')
                ->with($this->eagerLoadsFor($itemType))
                ->tap($scope)
                ->take(self::PER_TYPE_LIMIT)
                ->get();

            foreach ($models as $model) {
                $hits[] = ['type' => $itemType, 'model' => $model];
            }
        }

        return $hits;
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function textSearch(string $type, string $term): Collection
    {
        if (! $this->allowed($type)) {
            return collect();
        }

        $class = self::TYPES[$type];

        // Select only the base table's columns: the TextSearch scope joins
        // related tables, and without this their columns bleed into the model
        // and clobber id/asset_tag.
        return $class::query()
            ->select((new $class)->getTable().'.*')
            ->with($this->eagerLoadsFor($type))
            ->TextSearch($term)
            ->take(self::PER_TYPE_LIMIT)
            ->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function nameSearch(string $type, string $term): Collection
    {
        if (! $this->allowed($type)) {
            return collect();
        }

        $class = self::TYPES[$type];

        return $class::query()
            ->where('name', 'LIKE', '%'.$term.'%')
            ->take(self::PER_TYPE_LIMIT)
            ->get();
    }

    /**
     * Cap each type and drop duplicates — an asset can be reached by name, by
     * its location, by its category and by its model within one query.
     *
     * @param  Collection<int, array{type: string, model: object}>  $results
     * @return Collection<int, array{type: string, model: object}>
     */
    private function capAndDeduplicate(Collection $results): Collection
    {
        $seen = [];
        $counts = [];

        return $results->filter(function (array $hit) use (&$seen, &$counts) {
            $key = $hit['type'].':'.$hit['model']->id;

            if (isset($seen[$key])) {
                return false;
            }

            $counts[$hit['type']] = ($counts[$hit['type']] ?? 0) + 1;

            if ($counts[$hit['type']] > self::PER_TYPE_LIMIT) {
                return false;
            }

            $seen[$key] = true;

            return true;
        })->values();
    }

    private function typeOf(object $model): ?string
    {
        foreach (self::TYPES as $type => $class) {
            if ($model instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    private function allowed(string $type): bool
    {
        return Gate::allows('index', self::TYPES[$type]);
    }

    /**
     * @return array<int, string>
     */
    private function eagerLoadsFor(string $type): array
    {
        return match ($type) {
            'asset' => ['model.category', 'location', 'assignedTo'],
            'accessory', 'component', 'consumable' => ['category', 'location'],
            default => [],
        };
    }
}
