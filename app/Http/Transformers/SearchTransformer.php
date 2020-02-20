<?php

namespace App\Http\Transformers;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use Gate;
use App\Helpers\Helper;

class SearchTransformer
{

    function transformLocation($object)
    {
        if (isset($object->location_id)) {
            $loc = Location::where('id', '=', $object->location_id)->first();
        } elseif (isset($object->rtd_location_id)) {
            $loc = Location::where('id', '=', $object->rtd_location_id)->first();
        } else {
            return null;
        }
        return [
            "name" => $loc->name,
            "id" => $loc->id
        ];
    }

    function transformAssetAssignedTo($asset)
    {
        if ($asset->assigned_to) {
            // App\\Models\\<class-name> ~> lower(class-name)
            $type_str = str_replace('\\', '', $asset->assigned_type);
            $type_str = str_replace('AppModels', '', $type_str);
            $type_str = strtolower($type_str);
            if ($type_str == 'user') {
                $u = User::where('id', '=', $asset->assigned_to)->first();
                $name_str = $u->first_name . ' ' . $u->last_name;
            } elseif ($type_str == 'asset') {
                $asset = Asset::where('id', '=', $asset->assigned_to)->first();
                $name_str = $asset->name;
            } elseif ($type_str == 'location') {
                $location = Location::where('id', '=', $asset->assigned_to)->first();
                $name_str = $location->name;
            } else {
                return null;
            }
            return [
                'id' => $asset->assigned_to,
                'type' => $type_str,
                'name' => $name_str,
            ];
        } else {
            return null;
        }

    }

    function transformAsset($asset)
    {
        $model = AssetModel::where('id', '=', $asset->model_id)->first();
        $category = Category::where('id', '=', $model->category_id)->first();

        return [
            "id" => $asset['id'],
            "tag" => $asset->asset_tag,
            "name" => $asset['name'],
            "type" => 'Asset',
            "api" => 'hardware',
            "location" => $this->transformLocation($asset),
            "assigned_to" => $this->transformAssetAssignedTo($asset),
            "model" => $model,
            "category" => $category,
            "available_actions" => [
                'checkout' => (bool)Gate::allows('checkout', Asset::class) and (bool)$asset->availableForCheckout(),
                'checkin' => (bool)Gate::allows('checkin', Asset::class) and $asset->assigned_to,
                'clone' => Gate::allows('create', Asset::class) ? true : false,
                'restore' => false,
                'update' => (bool)Gate::allows('update', Asset::class),
                'delete' => (bool)Gate::allows('delete', Asset::class),
                'print' => true,
            ],
            'user_can_checkout' => (bool)$asset->availableForCheckout()
        ];
    }

    function transformAccessory($accessory)
    {
        return [
            "name" => $accessory['name'],
            "category" => Category::where('id', '=', $accessory->category_id)->first(),
            "tag" => Helper::expand_tag('AC-' . $accessory->id),
            "type" => 'Accessory',
            "api" => 'accessories',
            "id" => $accessory['id'],
            "location" => $this->transformLocation($accessory),
            "available_actions" => [
                'checkout' => Gate::allows('checkout', Accessory::class) ? $accessory->numRemaining() > 0 : false,
                'checkin' => false,
                'update' => Gate::allows('update', Accessory::class) ? true : false,
                'delete' => Gate::allows('delete', Accessory::class) ? true : false,
                'print' => true,
            ]
        ];
    }

    function transformConsumable($consumable)
    {
        return [
            "name" => $consumable['name'],
            "category" => Category::where('id', '=', $consumable->category_id)->first(),
            "tag" => Helper::expand_tag('CS-' . $consumable->id),
            "type" => 'Consumable',
            "api" => 'consumables',
            "id" => $consumable['id'],
            "available" => $consumable->qty,
            "location" => $this->transformLocation($consumable),
            "available_actions" => [
                'checkout' => Gate::allows('checkout', Consumable::class) ? $consumable->numRemaining() > 0 : false,
                'checkin' => false,
                'update' => Gate::allows('update', Consumable::class) ? true : false,
                'delete' => Gate::allows('delete', Consumable::class) ? true : false,
                'print' => true,
            ]
        ];
    }

    function transformComponent($component)
    {
        return [
            "name" => $component['name'],
            "category" => Category::where('id', '=', $component->category_id)->first(),
            "tag" => Helper::expand_tag('CM-' . $component->id),
            "type" => 'Component',
            "api" => 'components',
            "id" => $component['id'],
            "location" => $this->transformLocation($component),
            "available_actions" => [
                'checkout' => Gate::allows('checkout', Component::class) ? $component->numRemaining() > 0 : false,
                'checkin' => false,
                'update' => (bool)Gate::allows('update', Component::class),
                'delete' => (bool)Gate::allows('delete', Component::class),
                'print' => true,
            ]
        ];
    }

    public function transformSearchResult($data, $total_count)
    {
        $result = [];
        foreach ($data as $e) {
            switch ($e['type']) {
                case "asset":
                    array_push($result, $this->transformAsset($e['object']));
                    break;
                case "accessory":
                    array_push($result, $this->transformAccessory($e['object']));
                    break;
                case "component":
                    array_push($result, $this->transformComponent($e['object']));
                    break;
                case "consumable":
                    array_push($result, $this->transformConsumable($e['object']));
                    break;
                default:
                    break;
            }
        }

        return (new DatatablesTransformer())->transformDatatables($result, $total_count);
    }

}
