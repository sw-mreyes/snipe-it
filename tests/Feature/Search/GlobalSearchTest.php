<?php

namespace Tests\Feature\Search;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\User;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    public function test_api_search_returns_matching_asset()
    {
        Asset::factory()->create(['asset_tag' => 'GLOBALTEST123']);

        $this->actingAsForApi(User::factory()->viewAssets()->create())
            ->getJson(route('api.search.index', ['search' => 'GLOBALTEST123']))
            ->assertOk()
            ->assertJsonFragment(['identifier' => 'GLOBALTEST123', 'type' => 'asset']);
    }

    public function test_api_search_excludes_types_the_user_cannot_view()
    {
        Asset::factory()->create(['asset_tag' => 'GLOBALTEST123']);

        // User has no view permissions on any entity -> nothing comes back.
        $this->actingAsForApi(User::factory()->create())
            ->getJson(route('api.search.index', ['search' => 'GLOBALTEST123']))
            ->assertOk()
            ->assertJsonFragment(['total' => 0]);
    }

    public function test_api_search_returns_categories_and_asset_models()
    {
        Category::factory()->create(['name' => 'GLOBALCATTEST', 'category_type' => 'asset']);
        AssetModel::factory()->create(['name' => 'GLOBALMODELTEST']);

        $actor = $this->actingAsForApi(User::factory()->superuser()->create());

        $actor->getJson(route('api.search.index', ['search' => 'GLOBALCATTEST']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'GLOBALCATTEST', 'type' => 'category']);

        $actor->getJson(route('api.search.index', ['search' => 'GLOBALMODELTEST']))
            ->assertOk()
            ->assertJsonFragment(['name' => 'GLOBALMODELTEST', 'type' => 'assetModel']);
    }

    public function test_api_search_emits_asset_action_urls_and_flags()
    {
        Asset::factory()->create(['asset_tag' => 'ACTIONTEST123']);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.search.index', ['search' => 'ACTIONTEST123']))
            ->assertOk()
            // The asset row carries checkout/checkin/print URLs and matching flags.
            ->assertJsonPath('rows.0.type', 'asset')
            ->assertJsonPath('rows.0.available_actions.checkout', true)
            ->assertJsonPath('rows.0.available_actions.checkin', true)
            ->assertJsonPath('rows.0.available_actions.print', true)
            ->assertJsonFragment(['print_url' => route('network-label.asset', Asset::where('asset_tag', 'ACTIONTEST123')->first()->id)]);
    }

    public function test_web_search_page_renders_the_results_table()
    {
        $this->actingAs(User::factory()->viewAssets()->create())
            ->get(route('search', ['search' => 'GLOBALTEST123']))
            ->assertOk()
            // The page renders the bootstrap-table shell pointed at the search API.
            ->assertSee('api/v1/search')
            ->assertSee('globalSearchTable');
    }
}
