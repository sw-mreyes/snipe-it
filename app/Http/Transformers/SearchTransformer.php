<?php

namespace App\Http\Transformers;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Normalizes heterogeneous global-search results (custom fork feature) into a
 * single bootstrap-table feed. Each row carries its entity `type` so the UI
 * can render the correct links and row actions.
 */
class SearchTransformer
{
    /**
     * @param  Collection  $results  collection of ['type' => string, 'model' => Model]
     */
    public function transformSearchResults(Collection $results, $total)
    {
        $rows = [];
        foreach ($results as $result) {
            $rows[] = $this->transformResult($result['type'], $result['model']);
        }

        return (new DatatablesTransformer)->transformDatatables($rows, $total);
    }

    private function transformResult(string $type, $model): array
    {
        return [
            'type' => $type,
            'id' => (int) $model->id,
            'name' => e($this->nameFor($type, $model)),
            'identifier' => $type === 'asset' ? e($model->asset_tag) : null,
            'category' => ($type !== 'asset' && $model->category) ? e($model->category->name) : null,
            'location' => $model->location ? e($model->location->name) : null,
            'assigned_to' => $this->assignedToFor($type, $model),
            'view_url' => $this->viewUrlFor($type, $model),
            'available_actions' => $this->actionsFor($type, $model),
        ];
    }

    private function nameFor(string $type, $model): string
    {
        if ($type === 'asset') {
            return $model->present()->fullName ?: (string) $model->asset_tag;
        }

        return (string) $model->name;
    }

    private function assignedToFor(string $type, $model): ?string
    {
        if ($type !== 'asset' || ! $model->assignedTo) {
            return null;
        }

        if ($model->assigned_type === User::class) {
            return e($model->assignedTo->present()->fullName);
        }

        return $model->assignedTo->name ? e($model->assignedTo->name) : null;
    }

    private function viewUrlFor(string $type, $model): string
    {
        return match ($type) {
            'asset' => route('hardware.show', $model->id),
            'accessory' => route('accessories.show', $model->id),
            'component' => route('components.show', $model->id),
            'consumable' => route('consumables.show', $model->id),
            default => '#',
        };
    }

    private function actionsFor(string $type, $model): array
    {
        $modelClass = get_class($model);

        return [
            'view' => Gate::allows('view', $model),
            'update' => Gate::allows('update', $model),
            // Only assets/accessories/consumables are checkoutable in this set.
            'checkout' => $type !== 'component' && Gate::allows('checkout', $modelClass),
        ];
    }
}
