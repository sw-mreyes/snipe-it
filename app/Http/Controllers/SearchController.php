<?php

namespace App\Http\Controllers;

use App\Services\GlobalSearchService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Global cross-entity search page (custom fork feature).
 *
 * Renders results server-side using the shared GlobalSearchService, which only
 * returns entity types the current user is allowed to view.
 */
class SearchController extends Controller
{
    public function globalSearch(Request $request, GlobalSearchService $search): View
    {
        $query = trim((string) $request->input('search', ''));
        $results = $search->search($query);

        return view('global_search')
            ->with('query', $query)
            ->with('results', $results);
    }
}
