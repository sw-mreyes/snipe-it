<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Transformers\SearchTransformer;
use App\Services\GlobalSearchService;
use Illuminate\Http\Request;

/**
 * Cross-entity global search API (custom fork feature).
 *
 * Delegates the query to GlobalSearchService (shared with the web controller)
 * and returns a single normalized, deduplicated, type-tagged result set for
 * the bootstrap-table UI.
 */
class SearchController extends Controller
{
    public function index(Request $request, GlobalSearchService $search)
    {
        $results = $search->search((string) $request->input('search', ''));

        return (new SearchTransformer)->transformSearchResults($results, $results->count());
    }
}
