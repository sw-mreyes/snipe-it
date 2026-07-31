<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Global cross-entity search page (custom fork feature).
 *
 * Renders the results shell only; the bootstrap-table fetches rows from the
 * search API, which uses the shared GlobalSearchService and returns just the
 * entity types the current user is allowed to see. No authorization is applied
 * here for that reason — an unprivileged user gets an empty table, not a 403.
 */
class SearchController extends Controller
{
    public function index(Request $request): View
    {
        return view('search.index')
            ->with('query', trim((string) $request->input('search', '')));
    }
}
