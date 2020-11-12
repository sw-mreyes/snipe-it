<?php

namespace App\Http\Controllers;

use App\Http\Transformers\SearchTransformer;
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
use Barryvdh\Debugbar\Middleware\Debugbar;

/** This controller handles all actions related to the pagewide search.
 *
 * @version    v1.0
 * @author [M. Reyes] [<mreyes@schutzwerk.com>]
 */
class SearchController extends Controller
{
    function globalSearch()
    {
        // Check index permissions 
        // TODO:    instead of failing, we could also just show the
        //          stuff the user is authorized to see ?
        //
        $this->authorize('index', Asset::class);
        $this->authorize('index', Accessory::class);
        $this->authorize('index', Component::class);
        $this->authorize('index', Consumable::class);

        $search = request('search');

        return view('global_search', [
            'query' => $search
        ]);
    }
}
