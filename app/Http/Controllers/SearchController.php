<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Global cross-entity search page (custom fork feature).
 *
 * Renders the results shell; the bootstrap-table fetches rows from the search
 * API (Api\SearchController), which uses the shared GlobalSearchService and only
 * returns entity types the current user is allowed to view.
 */
class SearchController extends Controller
{
    public function globalSearch(Request $request): View
    {
        $query = trim((string) $request->input('search', ''));

        return view('global_search')->with('query', $query);
    }
}
