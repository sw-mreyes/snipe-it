<?php

namespace Tests\Feature\Dashboard\Api;

use App\Models\Company;
use App\Models\User;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Narrow endpoint backing the dashboard Companies summary widget.
 * See Api\DashboardController for the auth + payload rationale that
 * splits this from the general-purpose api.companies.index.
 */
class CompaniesWidgetTest extends TestCase
{
    public function test_user_without_any_view_permission_is_forbidden()
    {
        Passport::actingAs(User::factory()->create());

        $this->getJson(route('api.dashboard.companies'))
            ->assertForbidden();
    }

    public function test_scoped_viewer_only_gets_count_columns_for_viewable_types()
    {
        Company::factory()->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $row = $this->getJson(route('api.dashboard.companies'))
            ->assertOk()
            ->json('rows.0');

        $this->assertArrayHasKey('accessories_count', $row);
        $this->assertArrayNotHasKey('assets_count', $row);
        $this->assertArrayNotHasKey('consumables_count', $row);
        $this->assertArrayNotHasKey('components_count', $row);
        $this->assertArrayNotHasKey('licenses_count', $row);
        $this->assertArrayNotHasKey('users_count', $row);
    }

    public function test_row_carries_available_actions_view_flag_and_no_extra_fields()
    {
        Company::factory()->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $row = $this->getJson(route('api.dashboard.companies'))
            ->assertOk()
            ->json('rows.0');

        // Widget-shape whitelist. Anything beyond these fields is a
        // widening that leaks past what the widget renders. Extend
        // this list intentionally when adding a widget column, not
        // accidentally by extending the transformer.
        $allowed = ['id', 'name', 'available_actions', 'accessories_count'];
        $this->assertSame(sort($allowed) ? $allowed : $allowed, array_values(collect(array_keys($row))->sort()->values()->all()));
        $this->assertFalse($row['available_actions']['view']);
    }

    public function test_admin_sees_view_flag_true()
    {
        Company::factory()->create();

        Passport::actingAs(User::factory()->superuser()->create());

        $row = $this->getJson(route('api.dashboard.companies'))
            ->assertOk()
            ->json('rows.0');

        $this->assertTrue($row['available_actions']['view']);
    }

    public function test_search_and_filter_params_are_ignored()
    {
        Company::factory()->count(3)->create();

        Passport::actingAs(User::factory()->viewAccessories()->create());

        $unfilteredTotal = $this->getJson(route('api.dashboard.companies'))
            ->assertOk()
            ->json('total');
        $probeTotal = $this->getJson(route('api.dashboard.companies', [
            'search' => 'never-matches',
            'filter' => 'never-matches',
            'name' => 'never-matches',
            'email' => 'never-matches',
            'created_by' => 999_999,
            'tag_color' => '#ffffff',
        ]))
            ->assertOk()
            ->json('total');

        $this->assertSame($unfilteredTotal, $probeTotal);
    }
}
