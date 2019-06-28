<?php

namespace App\Http\Controllers;

use Auth;
use DB;
use Input;

use App\Helpers\Helper;
use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\Company;
use App\Models\User;
use App\Models\AssetModel;
use stdClass;

/** This controller handles all actions related to the pagewide search.
 *
 * @version    v1.0
 * @author [M. Reyes] [<mreyes@schutzwerk.com>]
 */
class SearchController extends Controller
{

    /**
     * allows the user to search for tags without leading zeroes:
     * SW-134 => SW-0000000134
     */
    function fix_tag_len($tag)
    {
        while (strlen($tag) < 13) {
            $split = explode('-', $tag);
            $tag = $split[0] . '-0' . $split[1];
        }
        return $tag;
    }

    function tag2id($tag)
    {
        $split = explode('-', $tag);
        $num_part = $split[1];

        while (substr($num_part, 0, 1) == '0') {
            $num_part = substr($num_part, 1);
        }

        $id = (int) $num_part;
        return $id;
    }

    function parse_asset($asset)
    {
        $e = new stdClass();
        $e->name = $asset->name;
        $model = AssetModel::where('id', '=', $asset->model_id)->first();
        $e->category = Category::where('id', '=', $model->category_id)->first()->name;;
        $e->tag = $asset->asset_tag;
        $e->type = 'Asset';
        $e->id = $asset->id;
        return $e;
    }
    function parse_accessory($accessory)
    {
        $e = new stdClass();
        $e->name = $accessory->name;
        $e->category = Category::where('id', '=', $accessory->category_id)->first()->name;
        $e->tag = $this->fix_tag_len('AC-' . $accessory->id);
        $e->type = 'Accessory';
        $e->id = $accessory->id;
        return $e;
    }
    function parse_component($component)
    {
        $e = new stdClass();
        $e->name = $component->name;
        $e->category = Category::where('id', '=', $component->category_id)->first()->name;
        $e->tag = $this->fix_tag_len('CM-' . $component->id);
        $e->type = 'Component';
        $e->id = $component->id;
        return $e;
    }
    function parse_consumable($consumable)
    {
        $e = new stdClass();
        $e->name = $consumable->name;
        $e->category = Category::where('id', '=', $consumable->category_id)->first()->name;
        $e->tag = $this->fix_tag_len('CS-' . $consumable->id);
        $e->type = 'Consumable';
        $e->id = $consumable->id;
        return $e;
    }

    function parse_category($category)
    {
        $e = new stdClass();
        $e->name = $category->name;
        $e->category = '-';
        $e->tag = '-';
        $e->type = 'Category';
        $e->id = $category->id;
        return $e;
    }

    function parse_location($location)
    {
        $e = new stdClass();
        $e->name = $location->name;
        $e->category = '-';
        $e->tag = $this->fix_tag_len('BX-' . $location->id);
        $e->type = 'Location';
        $e->id = $location->id;
        return $e;
    }

    /**
     * Get an entity by its tag.
     * Works for assets, accessories, consumables, components, locations
     */
    function get_by_tag($tag)
    {
        $tag = strtoupper($tag);
        switch (substr($tag, 0, 2)) {
            case 'SW':
                $asset = Asset::where('asset_tag', '=', $this->fix_tag_len($tag))->first();
                if ($asset) {
                    return $this->parse_asset($asset);
                }
                break;
            case 'AC':
                $accessory = Accessory::find($this->tag2id($tag));
                if ($accessory) {
                    return $this->parse_accessory($accessory);
                }
                break;
            case 'CM':
                $component = Component::find($this->tag2id($tag));
                if ($component) {
                    return $this->parse_component($component);
                }
                break;
            case 'CS':
                $consumable = Consumable::find($this->tag2id($tag));
                if ($consumable) {
                    return $this->parse_consumable($consumable);
                }
                break;
            case 'BX':
                $location = Location::find($this->tag2id($tag));
                if ($location) {
                    return $this->parse_location($location);
                }
                break;
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

        $assets = Asset::where('name', 'LIKE', "%{$str}%")
            ->orWhere('notes', 'LIKE', "%{$str}%")
            ->get();

        $accesories = Accessory::where('name', 'LIKE', "%{$str}%")->get();
        $components = Component::where('name', 'LIKE', "%{$str}%")->get();
        $consumables = Consumable::where('name', 'LIKE', "%{$str}%")->get();
        $locations = Location::where('name', 'LIKE', "%{$str}%")->get();
        $categories = Category::where('name', 'LIKE', "%{$str}%")->get();

        foreach ($assets as $asset) array_push($results, $this->parse_asset($asset));
        foreach ($accesories as $acc) array_push($results, $this->parse_accessory($acc));
        foreach ($components as $cmp) array_push($results, $this->parse_component($cmp));
        foreach ($consumables as $cs) array_push($results, $this->parse_consumable($cs));
        foreach ($locations as $loc) array_push($results, $this->parse_location($loc));
        foreach ($categories as $cat) array_push($results, $this->parse_category($cat));      

        return $results;
    }


    function globalSearch()
    {
        $search = Input::get('search');
        $tag_pattern_result = [];
        $search_result = [];

        $terms = explode(',', $search);
        foreach ($terms as $term) {
            // Check if the term is a tag
            if ($n_matches = preg_match('/(?:BX|SW|AC|CS|CM|bx|sw|ac|cs|cm)-[0-9]{1,10}/', $term, $tag_pattern_result)) {
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

        return view('global_search', [
            'search_result' => $search_result,
            'query' => $search
        ]);
    }
}
