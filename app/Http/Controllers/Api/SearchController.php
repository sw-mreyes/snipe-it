<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\SearchTransformer;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cross-entity global search API (custom fork feature).
 *
 * Searches assets, accessories, components and consumables in one call, using
 * each model's existing TextSearch scope, and returns a single normalized,
 * deduplicated, type-tagged result set for the bootstrap-table UI.
 *
 * Only entity types the current user is allowed to view are queried, so the
 * search degrades gracefully rather than failing closed.
 */
class SearchController extends Controller
{
    /**
     * Per-type cap so one entity type cannot dominate the combined result.
     */
    private const PER_TYPE_LIMIT = 50;

    public function index(Request $request)
    {
        $term = trim((string) $request->input('search', ''));

        $results = collect();

        if ($term === '') {
            return (new SearchTransformer)->transformSearchResults($results, 0);
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

        $total = $results->count();

        return (new SearchTransformer)->transformSearchResults($results, $total);
    }

    /**
     * @return array<string, class-string>
     */
    private function searchableTypes(): array
    {
        return [
            'asset' => Asset::class,
            'accessory' => Accessory::class,
            'component' => Component::class,
            'consumable' => Consumable::class,
        ];
    }

    private function eagerLoadsFor(string $type): array
    {
        return match ($type) {
            'asset' => ['model', 'location', 'assignedTo'],
            default => ['category', 'location'],
        };
    }
}
