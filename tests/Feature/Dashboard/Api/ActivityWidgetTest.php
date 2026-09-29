<?php

namespace Tests\Feature\Dashboard\Api;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Purpose-built endpoint that backs the dashboard Recent Activity
 * widget. See Api\DashboardController for the auth + query-surface
 * rationale. Split from the general-purpose api.activity.index so
 * scoped viewers do not gain filter / search / sort probes against
 * activity attributes the widget does not surface.
 */
class ActivityWidgetTest extends TestCase
{
    public function test_user_without_any_view_permission_is_forbidden()
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson(route('api.dashboard.activity'))
            ->assertForbidden();
    }

    public function test_scoped_viewer_sees_only_activity_for_viewable_types()
    {
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create();

        Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'ASSET_ROW_SENTINEL',
        ]);
        Actionlog::factory()->create([
            'item_type' => Accessory::class,
            'item_id' => $accessory->id,
            'action_type' => 'checkout',
            'note' => 'ACCESSORY_ROW_SENTINEL',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $body = $this->getJson(route('api.dashboard.activity'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('ACCESSORY_ROW_SENTINEL', $body);
        $this->assertStringNotContainsString('ASSET_ROW_SENTINEL', $body);
    }

    public function test_activity_view_holder_sees_every_type_without_filtering()
    {
        // Give the caller viewAccessories (so they pass the widget's
        // canViewUsersAndCheckoutables entry gate) plus canViewReports
        // (which resolves Gate::allows('activity.view') to true). The
        // activity.view branch skips the viewable-type filter, so an
        // Asset row should appear even though this user cannot view
        // assets directly.
        $asset = Asset::factory()->create();

        Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'ASSET_ROW_SENTINEL',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->canViewReports()->create());

        $this->getJson(route('api.dashboard.activity'))
            ->assertOk()
            ->assertSee('ASSET_ROW_SENTINEL');
    }

    public function test_limit_caps_at_fifty_regardless_of_query_string()
    {
        Actionlog::factory()->count(60)->create([
            'item_type' => Accessory::class,
            'item_id' => Accessory::factory()->create()->id,
            'action_type' => 'checkout',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $rows = $this->getJson(route('api.dashboard.activity', ['limit' => 500]))
            ->assertOk()
            ->json('rows');

        $this->assertLessThanOrEqual(50, count($rows));
    }

    public function test_search_and_filter_params_do_not_narrow_the_result_set()
    {
        // Probe attempt: scoped viewer trying to run a note-text search
        // through the dashboard endpoint. The widget does not need it,
        // so the endpoint ignores it. Same-caller results should be
        // identical with and without the probe.
        $accessory = Accessory::factory()->create();
        Actionlog::factory()->count(3)->create([
            'item_type' => Accessory::class,
            'item_id' => $accessory->id,
            'action_type' => 'checkout',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $unfiltered = $this->getJson(route('api.dashboard.activity'))
            ->assertOk()
            ->json('total');
        $withProbe = $this->getJson(route('api.dashboard.activity', [
            'search' => 'never-matches-anything',
            'filter' => 'never-matches-anything',
            'action_type' => 'delete',
            'created_by' => 999_999,
        ]))
            ->assertOk()
            ->json('total');

        $this->assertSame($unfiltered, $withProbe);
    }
}
