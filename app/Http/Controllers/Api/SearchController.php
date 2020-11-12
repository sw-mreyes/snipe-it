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


/** This controller handles all actions related to the global search.
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

    function wrap_results($assets, $accessories, $components, $consumables)
    {
        $results = [];
        foreach ($assets as $asset) array_push($results, $this->wrap("asset", $asset));
        foreach ($accessories as $acc) array_push($results, $this->wrap("accessory", $acc));
        foreach ($components as $cmp) array_push($results, $this->wrap("component", $cmp));
        foreach ($consumables as $cs) array_push($results, $this->wrap("consumable", $cs));
        return $results;
    }

    function parse_category($category)
    {
        $results = [];
        //
        $assets = Asset::select('assets.*')->whereNull('assets.deleted_at')->join('models', 'models.id', '=', 'model_id')->where('category_id', '=', $category->id)->get();
        $accessories = Accessory::where('category_id', '=', $category->id)->get();
        $components = Component::where('category_id', '=', $category->id)->get();
        $consumables = Consumable::where('category_id', '=', $category->id)->get();
        return $this->wrap_results($assets, $accessories, $components, $consumables);
    }

    function parse_location($location)
    {
        $assets = Asset::where('location_id', '=', $location->id)->get();
        $accessories = Accessory::where('location_id', '=', $location->id)->get();
        $components = Component::where('location_id', '=', $location->id)->get();
        $consumables = Consumable::where('location_id', '=', $location->id)->get();
        return $this->wrap_results($assets, $accessories, $components, $consumables);
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
                $accessory = Accessory::find(Helper::tag2id($tag));
                if ($accessory) {
                    return $this->wrap("accessory", $accessory);
                }
                break;
            case 'CM':
                $component = Component::find(Helper::tag2id($tag));
                if ($component) {
                    return $this->wrap("component", $component);
                }
                break;
            case 'CS':
                $consumable = Consumable::find(Helper::tag2id($tag));
                if ($consumable) {
                    return $this->wrap("consumable", $consumable);
                }
                break;
            /*case 'BX':
                $location = Location::find(Helper::tag2id($tag));
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
     * @param $search_string string string to search for
     * @return array
     */
    function search_string($search_string)
    {
        //  Direct results
        //
        $assets = Asset::whereNull('deleted_at')->where('name', 'LIKE', "%{$search_string}%")
            ->orWhere('notes', 'LIKE', "%{$search_string}%")
            ->get();
        $accessories = Accessory::where('name', 'LIKE', "%{$search_string}%")->get();
        $components = Component::where('name', 'LIKE', "%{$search_string}%")->get();
        $consumables = Consumable::where('name', 'LIKE', "%{$search_string}%")->get();
        //
        $results = $this->wrap_results($assets, $accessories, $components, $consumables);

        // Indirect results
        $locations = Location::where('name', 'LIKE', "%{$search_string}%")->get();
        $categories = Category::where('name', 'LIKE', "%{$search_string}%")->get();
        $models = AssetModel::where('name', 'LIKE', "%{$search_string}%")->get();
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

    private function remove_duplicates($data)
    {
        $result = array();
        // we only need to check assets since only those will be queried
        // multiple times.
        $asset_ids = [];
        foreach ($data as $entry) {
            if ($entry['type'] == 'asset'){
                $id = $entry['object']['id'];
                if(array_key_exists($id,$asset_ids)) continue;
                else $asset_ids[$id] = 0;
            }
            array_push($result, $entry);
        }
        return $result;
    }

    public function search()
    {

        $search = request('search');
        $offset = request('offset');
        $limit =  request('limit');


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

        return (new SearchTransformer())->transformSearchResult($this->remove_duplicates($search_result), $total_count);
    }


}
