<?php

namespace App\Http\Transformers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use App\Services\GlobalSearch\ItemTag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Normalizes heterogeneous global-search results (custom fork feature) into a
 * single bootstrap-table feed. Every row carries its entity `type` so the UI
 * can render the right links, icon and row actions.
 */
class SearchTransformer
{
    /**
     * @param  Collection<int, array{type: string, model: object}>  $results
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
            'tag' => e(ItemTag::for($model)),
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

    private function nameFor(string $type, $model): string
    {
        if ($type === 'asset') {
            return $model->name ?: (string) $model->asset_tag;
        }

        return (string) $model->name;
    }

    /**
     * Assets carry their category through the model; the other item types have
     * it directly. Categories and models are their own category, so they show
     * nothing here.
     */
    private function categoryFor(string $type, $model): ?string
    {
        if ($type === 'asset') {
            return optional(optional($model->model)->category)->name
                ? e($model->model->category->name)
                : null;
        }

        if (in_array($type, ['category', 'assetModel', 'location'], true)) {
            return null;
        }

        return $model->category ? e($model->category->name) : null;
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
            'location' => route('locations.show', $model->id),
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
     * Checkin form URL. Only assets check in unambiguously by item id — for
     * accessories and components checkin is per assignment (a pivot id a search
     * row does not carry), so it is intentionally omitted for them.
     */
    private function checkinUrlFor(string $type, $model): ?string
    {
        return $type === 'asset' ? route('hardware.checkin.create', $model->id) : null;
    }

    /**
     * Network-label print URL, for every type the print server supports.
     */
    private function printUrlFor(string $type, $model): ?string
    {
        if (! Gate::allows('view', $model)) {
            return null;
        }

        return match ($type) {
            'asset' => route('network-label.asset', $model->id),
            'accessory' => route('network-label.accessory', $model->id),
            'component' => route('network-label.component', $model->id),
            'consumable' => route('network-label.consumable', $model->id),
            'location' => route('network-label.location', $model->id),
            default => null,
        };
    }

    private function actionsFor(string $type, $model): array
    {
        $checkoutable = in_array($type, ['asset', 'accessory', 'component', 'consumable'], true);
        $printable = in_array($type, ['asset', 'accessory', 'component', 'consumable', 'location'], true);

        $class = match ($type) {
            'asset' => Asset::class,
            'accessory' => Accessory::class,
            'component' => Component::class,
            'consumable' => Consumable::class,
            'location' => Location::class,
            default => get_class($model),
        };

        return [
            'view' => Gate::allows('view', $model),
            'update' => Gate::allows('update', $model),
            'checkout' => $checkoutable && Gate::allows('checkout', $class),
            // Asset checkin only (see checkinUrlFor).
            'checkin' => $type === 'asset' && Gate::allows('checkin', $class),
            'print' => $printable && Gate::allows('view', $model) && count((array) config('sw-label-printer.printers', [])) > 0,
        ];
    }
}
