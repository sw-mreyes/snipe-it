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
            'category' => $this->categoryFor($type, $model),
            'location' => ($model->location ?? null) ? e($model->location->name) : null,
            'assigned_to' => $this->assignedToFor($type, $model),
            'view_url' => $this->viewUrlFor($type, $model),
            'checkout_url' => $this->checkoutUrlFor($type, $model),
            'checkin_url' => $this->checkinUrlFor($type, $model),
            'print_url' => $this->printUrlFor($type, $model),
            'available_actions' => $this->actionsFor($type, $model),
        ];
    }

    private function categoryFor(string $type, $model): ?string
    {
        // Assets carry their category via the model; the others have it directly.
        if (in_array($type, ['asset', 'category', 'assetModel'], true)) {
            return null;
        }

        return $model->category ? e($model->category->name) : null;
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
            'category' => route('categories.show', $model->id),
            'assetModel' => route('models.show', $model->id),
            default => '#',
        };
    }

    /**
     * Checkout form URL for the checkoutable types (all take the item id).
     */
    private function checkoutUrlFor(string $type, $model): ?string
    {
        return match ($type) {
            'asset' => route('hardware.checkout.create', $model->id),
            'accessory' => route('accessories.checkout.show', $model->id),
            'component' => route('components.checkout.show', $model->id),
            'consumable' => route('consumables.checkout.show', $model->id),
            default => null,
        };
    }

    /**
     * Checkin form URL. Only assets check in unambiguously by item id; for
     * accessories/components checkin is per-assignment (a pivot id a search row
     * does not carry), so it is intentionally omitted here.
     */
    private function checkinUrlFor(string $type, $model): ?string
    {
        return $type === 'asset' ? route('hardware.checkin.create', $model->id) : null;
    }

    /**
     * Network-label print URL (assets only, mirrors the asset view / API).
     */
    private function printUrlFor(string $type, $model): ?string
    {
        return $type === 'asset' ? route('network-label.asset', $model->id) : null;
    }

    private function actionsFor(string $type, $model): array
    {
        $modelClass = get_class($model);

        // Categories and asset-models are view-only in this set.
        $checkoutable = in_array($type, ['asset', 'accessory', 'component', 'consumable'], true);

        return [
            'view' => Gate::allows('view', $model),
            'update' => Gate::allows('update', $model),
            'checkout' => $checkoutable && Gate::allows('checkout', $modelClass),
            // Asset checkin only (see checkinUrlFor).
            'checkin' => $type === 'asset' && Gate::allows('checkin', $modelClass),
            'print' => $type === 'asset' && Gate::allows('view', $model),
        ];
    }
}
