<?php

namespace Tests\Feature\Search;

use App\Models\Asset;
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

    public function test_web_search_page_renders_results()
    {
        Asset::factory()->create(['asset_tag' => 'GLOBALTEST123']);

        $this->actingAs(User::factory()->viewAssets()->create())
            ->get(route('search', ['search' => 'GLOBALTEST123']))
            ->assertOk()
            ->assertSee('GLOBALTEST123');
    }
}
