<?php

namespace Tests\Feature;

use App\Models\Accessory;
use App\Models\Actionlog;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Company;
use App\Models\License;
use App\Models\Location;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * The dashboard is reachable by any user who passes
 * canViewUsersAndCheckoutables (can view at least one
 * HasCalendarEvents source). Each widget's API endpoint has to
 * accept those callers too, or the widget renders an ugly "Could
 * not load results, 403" toast. This test covers the widening on
 * Api\CompaniesController@index, Api\LocationsController@index,
 * Api\CategoriesController@index, and Api\ReportsController@index
 * (recent activity).
 *
 * Transitive-visibility reasoning: a user who can view any inventory
 * type inherently knows about the companies / locations / categories
 * those items belong to (they're attributes on every asset,
 * accessory, etc). CompanyableScope and FMCS location scoping filter
 * responses naturally.
 *
 * Recent activity has an additional server-side per-type filter for
 * scoped viewers so an accessory-only user's feed only carries
 * actionlogs on types they can view.
 */
class DashboardWidgetVisibilityTest extends TestCase
{
    public function test_scoped_viewer_can_reach_companies_widget(): void
    {
        Company::factory()->count(2)->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $this->getJson(route('api.dashboard.companies'))->assertOk();
    }

    public function test_scoped_viewer_can_reach_locations_widget(): void
    {
        Location::factory()->count(2)->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $this->getJson(route('api.dashboard.locations'))->assertOk();
    }

    public function test_scoped_viewer_can_reach_categories_widget(): void
    {
        Category::factory()->count(2)->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $this->getJson(route('api.dashboard.categories'))->assertOk();
    }

    public function test_scoped_viewer_still_forbidden_on_general_index_endpoints(): void
    {
        // The dashboard now hits purpose-built api.dashboard.* twins.
        // A scoped viewer must still get 403 on the general-purpose
        // per-domain indexes, whose query surface exposes far more
        // than the widget renders. Regression guard for the widen
        // that used to live on these endpoints.
        Passport::actingAs(User::factory()->viewAccessories()->create());

        $this->getJson(route('api.companies.index'))->assertForbidden();
        $this->getJson(route('api.locations.index'))->assertForbidden();
        $this->getJson(route('api.categories.index'))->assertForbidden();
        $this->getJson(route('api.activity.index'))->assertForbidden();
    }

    public function test_permissionless_user_still_forbidden_on_each_widget_endpoint(): void
    {
        // A base user without any view grants still fails
        // canViewUsersAndCheckoutables and gets 403 on each widget
        // endpoint. Keeps the coarse gate meaningful.
        Passport::actingAs(User::factory()->create());

        $this->getJson(route('api.dashboard.companies'))->assertForbidden();
        $this->getJson(route('api.dashboard.locations'))->assertForbidden();
        $this->getJson(route('api.dashboard.categories'))->assertForbidden();
        $this->getJson(route('api.dashboard.activity'))->assertForbidden();
    }

    public function test_scoped_activity_viewer_only_sees_actionlogs_on_types_they_can_view(): void
    {
        // Seed one actionlog per relevant type. A user with only
        // accessories.view should see the Accessory row and nothing
        // else through the dashboard's recent-activity feed.
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create();
        $license = License::factory()->create();

        Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'SENTINEL_ASSET_ROW',
        ]);
        Actionlog::factory()->create([
            'item_type' => Accessory::class,
            'item_id' => $accessory->id,
            'action_type' => 'checkout',
            'note' => 'SENTINEL_ACCESSORY_ROW',
        ]);
        Actionlog::factory()->create([
            'item_type' => License::class,
            'item_id' => $license->id,
            'action_type' => 'checkout',
            'note' => 'SENTINEL_LICENSE_ROW',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $response = $this->getJson(route('api.dashboard.activity'))->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('SENTINEL_ACCESSORY_ROW', $body);
        $this->assertStringNotContainsString('SENTINEL_ASSET_ROW', $body);
        $this->assertStringNotContainsString('SENTINEL_LICENSE_ROW', $body);
    }

    public function test_scoped_viewer_sees_available_actions_view_false_on_lookup_rows(): void
    {
        // Row-level view flag drives the dashboard formatter's
        // decision to render the name as plain text (no link) so a
        // scoped viewer clicking through does not 403 on the show
        // controller they cannot reach. Same shape on Categories,
        // Locations, and Companies transformers. Category needs to
        // be an accessory-typed one so it survives the category_type
        // filter the API applies for scoped viewers.
        Category::factory()->create(['category_type' => 'accessory']);
        Location::factory()->create();
        Company::factory()->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $categoryRow = $this->getJson(route('api.dashboard.categories'))->assertOk()->json('rows.0');
        $locationRow = $this->getJson(route('api.dashboard.locations'))->assertOk()->json('rows.0');
        $companyRow = $this->getJson(route('api.dashboard.companies'))->assertOk()->json('rows.0');

        $this->assertFalse($categoryRow['available_actions']['view']);
        $this->assertFalse($locationRow['available_actions']['view']);
        $this->assertFalse($companyRow['available_actions']['view']);
    }

    public function test_scoped_categories_filter_excludes_types_the_viewer_cannot_view(): void
    {
        // Accessory-only viewer should see accessory-typed categories
        // but not asset / license / consumable / component ones. The
        // filter widens back out to unfiltered when the caller has
        // explicit categories.view.
        Category::factory()->create(['name' => 'Asset Cat', 'category_type' => 'asset']);
        Category::factory()->create(['name' => 'Accessory Cat', 'category_type' => 'accessory']);
        Category::factory()->create(['name' => 'License Cat', 'category_type' => 'license']);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $rows = $this->getJson(route('api.dashboard.categories'))->assertOk()->json('rows');
        $names = collect($rows)->pluck('name')->all();

        $this->assertContains('Accessory Cat', $names);
        $this->assertNotContains('Asset Cat', $names);
        $this->assertNotContains('License Cat', $names);
    }

    public function test_activity_row_carries_viewable_flag_per_item(): void
    {
        // Recent-activity's polymorphicItemFormatter renders name +
        // link when the caller can view the record, plain text when
        // they cannot. The flag flows through the transformer as
        // item.viewable / target.viewable on each row so a scoped
        // viewer's dashboard clicks do not 403.
        $accessory = Accessory::factory()->create();

        Actionlog::factory()->create([
            'item_type' => Accessory::class,
            'item_id' => $accessory->id,
            'action_type' => 'checkout',
        ]);

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $rows = $this->getJson(route('api.dashboard.activity'))->assertOk()->json('rows');
        $accessoryRows = collect($rows)->filter(fn ($r) => ($r['item']['type'] ?? null) === 'accessory')->values()->all();
        $this->assertNotEmpty($accessoryRows, 'accessory row must survive the scoped-viewer filter');
        foreach ($accessoryRows as $row) {
            $this->assertTrue($row['item']['viewable']);
        }
    }

    public function test_admin_sees_available_actions_view_true_on_lookup_rows(): void
    {
        Category::factory()->create();
        Location::factory()->create();
        Company::factory()->create();

        Passport::actingAs(User::factory()->superuser()->create());

        $categoryRow = $this->getJson(route('api.dashboard.categories'))->assertOk()->json('rows.0');
        $locationRow = $this->getJson(route('api.dashboard.locations'))->assertOk()->json('rows.0');
        $companyRow = $this->getJson(route('api.dashboard.companies'))->assertOk()->json('rows.0');

        $this->assertTrue($categoryRow['available_actions']['view']);
        $this->assertTrue($locationRow['available_actions']['view']);
        $this->assertTrue($companyRow['available_actions']['view']);
    }

    public function test_full_activity_view_still_sees_every_row(): void
    {
        // Superuser (implicit activity.view via hasAccess('admin'))
        // must still see the full unfiltered feed. Regression guard
        // for the widened gate.
        $asset = Asset::factory()->create();
        $accessory = Accessory::factory()->create();

        Actionlog::factory()->create([
            'item_type' => Asset::class,
            'item_id' => $asset->id,
            'action_type' => 'checkout',
            'note' => 'FULL_ASSET_ROW',
        ]);
        Actionlog::factory()->create([
            'item_type' => Accessory::class,
            'item_id' => $accessory->id,
            'action_type' => 'checkout',
            'note' => 'FULL_ACCESSORY_ROW',
        ]);

        Passport::actingAs(User::factory()->superuser()->create());

        $response = $this->getJson(route('api.activity.index'))->assertOk();
        $body = $response->getContent();

        $this->assertStringContainsString('FULL_ASSET_ROW', $body);
        $this->assertStringContainsString('FULL_ACCESSORY_ROW', $body);
    }
}
