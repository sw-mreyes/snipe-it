<?php

namespace App\Http\Controllers\Api;

use App\Helpers\Helper;
use App\Http\Requests\Request;
use App\Http\Transformers\DatatablesTransformer;
use App\Http\Transformers\SearchTransformer;
use Auth;
use DB;
use Input;


use \App\Models\Asset;
use \App\Models\Accessory;
use \App\Models\Component;
use \App\Models\Consumable;
use \App\Models\Location;
use \App\Models\Category;
use \App\Models\AssetModel;


use App\Http\Controllers\Controller;


/** This controller handles all actions related to the pagewide search.
 *
 * @version    v1.0
 * @author [M. Reyes] [<mreyes@schutzwerk.com>]
 */
class SearchController extends Controller
{
    function wrap($type, $obj)
    {
        return [
            'object' => $obj,
            'type' => $type,
        ];
    }

    function parse_category($category)
    {
        $results = [];
        //
        $assets = Asset::select('assets.*')->whereNull('assets.deleted_at')->join('models', 'models.id', '=', 'model_id')->where('category_id', '=', $category->id)->get();
        $accesories = Accessory::where('category_id', '=', $category->id)->get();
        $components = Component::where('category_id', '=', $category->id)->get();
        $consumables = Consumable::where('category_id', '=', $category->id)->get();
        foreach ($assets as $asset) array_push($results, $this->wrap("asset", $asset));
        foreach ($accesories as $acc) array_push($results, $this->wrap("accessory", $acc));
        foreach ($components as $cmp) array_push($results, $this->wrap("component", $cmp));
        foreach ($consumables as $cs) array_push($results, $this->wrap("consumable", $cs));
        //
        return $results;
    }

    function parse_location($location)
    {
        $results = [];
        //
        $assets = Asset::where('location_id', '=', $location->id)->get();
        $accesories = Accessory::where('location_id', '=', $location->id)->get();
        $components = Component::where('location_id', '=', $location->id)->get();
        $consumables = Consumable::where('location_id', '=', $location->id)->get();
        foreach ($assets as $asset) array_push($results, $this->wrap("asset", $asset));
        foreach ($accesories as $acc) array_push($results, $this->wrap("accessory", $acc));
        foreach ($components as $cmp) array_push($results, $this->wrap("component", $cmp));
        foreach ($consumables as $cs) array_push($results, $this->wrap("consumable", $cs));
        //
        return $results;
    }

    function parse_model($model)
    {
        $results = [];
        //
        $assets = Asset::whereNull('deleted_at')->where('model_id', '=', $model->id)->get();
        foreach ($assets as $asset) array_push($results, $this->wrap("asset", $asset));
        //
        return $results;
    }

    /**
     * Get an entity by its tag.
     * Works for assets, accessories, consumables, components.
     */
    function get_by_tag($tag)
    {
        $tag = strtoupper($tag);
        switch (substr($tag, 0, 2)) {
            case 'SW':
                $asset = Asset::whereNull('deleted_at')->where('asset_tag', '=', Helper::expand_tag($tag))->first();
                if ($asset) {
                    return $this->wrap("asset", $asset);
                }
                break;
            case 'AC':
                $accessory = Accessory::find($this->tag2id($tag));
                if ($accessory) {
                    return $this->wrap("asset", $accessory);
                }
                break;
            case 'CM':
                $component = Component::find($this->tag2id($tag));
                if ($component) {
                    return $this->wrap("component", $component);
                }
                break;
            case 'CS':
                $consumable = Consumable::find($this->tag2id($tag));
                if ($consumable) {
                    return $this->wrap("consumable", $consumable);
                }
                break;
            /*case 'BX':
                $location = Location::find($this->tag2id($tag));
                if ($location) {
                    return $this->parse_location($location);
                }
                break;*/
            default:
                return "This should never happen.";
        }

        // dont return the empty object
        return null;
    }

    /**
     * Search [assets, accessories, consumables, components, locations]
     * names (and asset notes) for the given string.
     *
     */
    function search_string($str)
    {
        $results = [];

        //  Direct results
        //
        $assets = Asset::whereNull('deleted_at')->where('name', 'LIKE', "%{$str}%")
            ->orWhere('notes', 'LIKE', "%{$str}%")
            ->get();
        $accesories = Accessory::where('name', 'LIKE', "%{$str}%")->get();
        $components = Component::where('name', 'LIKE', "%{$str}%")->get();
        $consumables = Consumable::where('name', 'LIKE', "%{$str}%")->get();
        foreach ($assets as $asset) array_push($results, $this->wrap("asset", $asset));
        foreach ($accesories as $acc) array_push($results, $this->wrap("accessory", $acc));
        foreach ($components as $cmp) array_push($results, $this->wrap("component", $cmp));
        foreach ($consumables as $cs) array_push($results, $this->wrap("consumable", $cs));

        // Indirect results
        $locations = Location::where('name', 'LIKE', "%{$str}%")->get();
        $categories = Category::where('name', 'LIKE', "%{$str}%")->get();
        $models = AssetModel::where('name', 'LIKE', "%{$str}%")->get();
        foreach ($locations as $loc) {
            $items = $this->parse_location($loc);
            if (count($items)) {
                array_push($results, ...$items);
            }
        }

        foreach ($categories as $cat) {
            $items = $this->parse_category($cat);
            if (count($items)) {
                array_push($results, ...$items);
            }
        }

        foreach ($models as $mdl) {
            $items = $this->parse_model($mdl);
            if (count($items)) {
                array_push($results, ...$items);
            }
        }

        //app('debugbar')->stopMeasure('gs_single_term');
        return $results;
    }

    public function search()
    {

        $search = Input::get('search');
        $offset = Input::get('offset');
        $limit = Input::get('limit');


        $tag_pattern_result = [];
        $search_result = [];
        $terms = explode(',', $search);
        foreach ($terms as $term) {
            // Check if the term is a tag
            if (preg_match('/(?:BX|SW|AC|CS|CM|bx|sw|ac|cs|cm)-[0-9]{1,10}/', $term, $tag_pattern_result)) {
                foreach ($tag_pattern_result as $tag) {
                    if ($result = $this->get_by_tag($tag)) {
                        array_push($search_result, $result);
                    }
                }
            } else {
                foreach ($this->search_string($term) as $result) {
                    array_push($search_result, $result);
                }
            }
        }

        $total_count = count($search_result);

        if ($offset) {
            while ($offset > 0) {
                array_shift($search_result);
                $offset--;
            }
        }
        if ($limit) {
            while (count($search_result) > $limit) {
                array_pop($search_result);
            }
        }

        return (new SearchTransformer())->transformSearchResult($search_result, $total_count);
    }


}
