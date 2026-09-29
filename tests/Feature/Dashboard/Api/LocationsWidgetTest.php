<?php

namespace Tests\Feature\Dashboard\Api;

use App\Models\Location;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Narrow endpoint backing the dashboard Locations summary widget.
 * See Api\DashboardController for the auth + payload rationale that
 * splits this from the general-purpose api.locations.index.
 */
class LocationsWidgetTest extends TestCase
{
    public function test_user_without_any_view_permission_is_forbidden()
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson(route('api.dashboard.locations'))
            ->assertForbidden();
    }

    public function test_scoped_viewer_only_gets_count_columns_for_viewable_types()
    {
        Location::factory()->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $row = $this->getJson(route('api.dashboard.locations'))
            ->assertOk()
            ->json('rows.0');

        $this->assertArrayHasKey('accessories_count', $row);
        $this->assertArrayNotHasKey('assets_count', $row);
        $this->assertArrayNotHasKey('assigned_assets_count', $row);
        $this->assertArrayNotHasKey('consumables_count', $row);
        $this->assertArrayNotHasKey('components_count', $row);
        $this->assertArrayNotHasKey('users_count', $row);
    }

    public function test_asset_viewer_gets_both_asset_count_columns()
    {
        Location::factory()->create();

        Passport::actingAs(User::factory()->viewAssets()->create());

        $row = $this->getJson(route('api.dashboard.locations'))
            ->assertOk()
            ->json('rows.0');

        $this->assertArrayHasKey('assets_count', $row);
        $this->assertArrayHasKey('assigned_assets_count', $row);
    }

    public function test_row_carries_available_actions_view_flag()
    {
        Location::factory()->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $row = $this->getJson(route('api.dashboard.locations'))
            ->assertOk()
            ->json('rows.0');

        $this->assertFalse($row['available_actions']['view']);
    }

    public function test_search_and_filter_params_are_ignored()
    {
        Location::factory()->count(3)->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $unfilteredTotal = $this->getJson(route('api.dashboard.locations'))
            ->assertOk()
            ->json('total');
        $probeTotal = $this->getJson(route('api.dashboard.locations', [
            'search' => 'never-matches',
            'filter' => 'never-matches',
            'name' => 'never-matches',
            'city' => 'never-matches',
            'created_by' => 999_999,
        ]))
            ->assertOk()
            ->json('total');

        $this->assertSame($unfilteredTotal, $probeTotal);
    }
}
