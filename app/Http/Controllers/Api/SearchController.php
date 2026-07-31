<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\SearchTransformer;
use App\Services\GlobalSearch\GlobalSearchService;
use Illuminate\Http\Request;

/**
 * Cross-entity global search API (custom fork feature).
 *
 * Delegates the query to GlobalSearchService (shared with the web controller)
 * and returns one normalized, deduplicated, type-tagged result set for the
 * bootstrap-table UI.
 */
class SearchController extends Controller
{
    public function index(Request $request, GlobalSearchService $search)
    {
        // The term is read from `q`, not `search`: bootstrap-table sends its own
        // (usually empty) `search` parameter for server-side pagination, which
        // would otherwise clobber the term baked into the table's data-url.
        // `q` wins so the table client cannot clobber the term; the `search`
        // fallback keeps direct API calls (?search=foo) working.
        $term = trim((string) $request->input('q', $request->input('search', '')));

        $results = $search->search($term);

        return (new SearchTransformer)->transformSearchResults($results, $results->count());
    }
}
